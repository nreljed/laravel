import * as React from "react";
import { Input } from "../ui/input";
import { Textarea } from "../ui/textarea";
import { NativeSelect, NativeSelectOption } from "../ui/native-select";
import { Switch } from "../ui/switch";
import { Checkbox } from "../ui/checkbox";
import { Button } from "../ui/button";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "../ui/collapsible";
import { Copy, Plus, Trash2, GripVertical, ChevronDown, ChevronRight } from "lucide-react";
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type UniqueIdentifier,
} from "@dnd-kit/core";
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
    Combobox,
    ComboboxChips,
    ComboboxChip,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxList,
    ComboboxItem,
    ComboboxEmpty,
    ComboboxValue,
    useComboboxAnchor,
} from "../ui/combobox";
import {
    humanizeFieldName,
    type FieldControlProps,
    type FieldContext,
    type FieldDefinition,
    type FieldOption,
    type FieldType,
    type ResourceRecord,
} from "../../lib/resource-schema";
import { FeaturedImageSelector } from "../shared/featured-image-selector";
import { GalleryUploader, type ServerGalleryImage } from "../shared/gallery-uploader";

/**
 * Built-in field controls, and the registry a consuming app extends.
 *
 * The registry exists so a module can add a type once and have every resource
 * in every app use it. The alternative - a `switch` in the renderer - makes
 * "add a colour picker" a platform release, which is the coupling this kit is
 * meant to remove.
 *
 * Registration is module-scope and idempotent, and `ResourceForm` imports this
 * file, so the built-ins are always present. A `registerFieldType` after first
 * render is picked up on the next one; nothing memoises the registry.
 */
export type FieldControl = React.ComponentType<FieldControlProps>;

export interface FieldTypeRegistration {
    control: FieldControl;
    /** Applies a default when the form value is `undefined`. */
    initialValue?: () => unknown;
}

const registry = new Map<FieldType, FieldTypeRegistration>();

/**
 * Register or replace a field type. Later registration wins, which is what lets
 * an app override a built-in without forking it.
 */
export function registerFieldType(type: FieldType, registration: FieldTypeRegistration): void {
    registry.set(type, registration);
}

export function getFieldType(type: FieldType): FieldTypeRegistration | undefined {
    return registry.get(type);
}

export function registeredFieldTypes(): FieldType[] {
    return [...registry.keys()];
}

/**
 * The empty value a freshly-registered control starts from - `[]` for the
 * multi-value pickers, `false` for a switch, `null` for raw JSON. Callers that
 * build a value map outside a form (the bulk-action dialog) seed from the same
 * registry the controls render through, so a new field type cannot end up with
 * a working control and an uninitialised value.
 */
export function fieldTypeInitialValue(type: FieldType): unknown {
    return registry.get(type)?.initialValue?.() ?? "";
}

function Label({ field, id, required }: { field: FieldDefinition; id: string; required?: boolean }) {
    const label = field.label ?? humanizeFieldName(field.name);

    if (field.hidden) {
        return null;
    }

    return (
        <label htmlFor={id} className="text-sm font-medium">
            {label}
            {required ? <span className="text-destructive"> *</span> : null}
        </label>
    );
}

function Control({ field, value, record, setValue, setField, id, name, disabled, error, errors }: FieldControlProps) {


    // A field's own `render` is the documented escape hatch and wins over the
    // registry and the `dynamic` type, because it is declared closest to the
    // field that needs it.
    if (field.render) {
        return <>{field.render({ field, value, record, setValue, setField })}</>;
    }


    // A `dynamic` type with no `render` deliberately falls through to the
    // registry check below rather than returning null: it is the same wiring
    // mistake as an unregistered type, and silence would read as an
    // intentionally blank field.
    const registration = registry.get(field.type);

    if (!registration) {
        // A missing type is a wiring mistake in the declaring app, and it should
        // say which field and which type rather than rendering an empty cell.
        return (
            <p className="text-sm text-destructive">
                No control registered for field type &quot;{field.type}&quot; (field &quot;{field.name}&quot;).
            </p>
        );
    }

    const Control_ = registration.control;

    return (
        <Control_
            {...{ field, value, record, setValue, setField, error, errors }}
            id={id}
            name={name}
            disabled={disabled}
        />
    );
}

function TextInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <Input
            id={id}
            name={name}
            disabled={disabled}
            value={value == null ? "" : String(value)}
            placeholder={field.placeholder}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

function TextareaInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <Textarea
            id={id}
            name={name}
            disabled={disabled}
            rows={4}
            placeholder={field.placeholder}
            value={value == null ? "" : String(value)}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

/**
 * Numbers are kept as strings in form state and converted on submit.
 *
 * `type="number"` plus a controlled value turns every intermediate state -
 * cleared field, "1.", "-", a pasted "1e" - into a value the browser refuses,
 * and React logs the change it could not apply. Keeping the raw string and
 * coercing in `serialize` avoids that entirely, and means an empty field
 * submits as `""` rather than `0` or `NaN`.
 */
function NumberInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <Input
            id={id}
            name={name}
            type="number"
            disabled={disabled}
            placeholder={field.placeholder}
            value={value == null ? "" : String(value)}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

/**
 * Money is an amount plus a currency, kept as two form values under
 * `<name>` and `<name>_currency`. Displayed to two decimals and submitted as
 * the raw string, so the rounding a column type would apply never happens
 * behind the component's back.
 *
 * The currency half is a `NativeSelect` when the field declares `options` (a
 * static list, e.g. the shop's order currencies) or an `optionsUrl` (a lazy
 * endpoint returning `{ value, label }[]`) - and falls back to free text so a
 * descriptor that does not constrain it (a legacy money column) still edits.
 * The amount input never changes.
 */
function MoneyInput({ field, id, name, disabled, value, setValue, setField, record }: FieldControlProps) {
    const currency = (record[`${name}_currency`] as string | undefined) ?? "";
    const label = field.label ?? humanizeFieldName(name);
    const { options, loaded, error } = useOptions(field);
    const selectable = Boolean(field.options?.length) || Boolean(field.optionsUrl);

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <Input
                    id={id}
                    name={name}
                    type="number"
                    step="0.01"
                    disabled={disabled}
                    placeholder={field.placeholder}
                    value={value == null ? "" : String(value)}
                    onChange={(event) => setValue(event.target.value)}
                />
                {selectable ? (
                    <NativeSelect
                        aria-label={`${label} currency`}
                        name={`${name}_currency`}
                        disabled={disabled || !loaded}
                        className="w-32"
                        value={currency}
                        onChange={(event) => setField(`${name}_currency`, event.target.value)}
                    >
                        <NativeSelectOption value="">Select currency</NativeSelectOption>
                        {options.map((option) => (
                            <NativeSelectOption key={String(option.value)} value={String(option.value)}>
                                {option.label}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                ) : (
                    <Input
                        aria-label={`${label} currency`}
                        name={`${name}_currency`}
                        disabled={disabled}
                        className="w-24"
                        placeholder="EUR"
                        value={currency}
                        onChange={(event) => setField(`${name}_currency`, event.target.value)}
                    />
                )}
            </div>
            {!field.options?.length && error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

function SelectInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    const { options, loaded, error } = useOptions(field);

    return (
        <div className="space-y-2">
            <NativeSelect
                id={id}
                name={name}
                disabled={disabled || (!field.options && !loaded)}
                value={value == null ? "" : String(value)}
                onChange={(event) => setValue(event.target.value)}
            >
                {field.placeholder ? (
                    <NativeSelectOption value="">{field.placeholder}</NativeSelectOption>
                ) : null}
                {options.map((option) => (
                    <NativeSelectOption key={String(option.value)} value={String(option.value)}>
                        {option.label}
                    </NativeSelectOption>
                ))}
            </NativeSelect>
            {!field.options?.length && error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

/**
 * `relation` is the schema's multi-value picker: an array of ids or keys held
 * in a searchable combobox and displayed as removable chips. Two option
 * sources, one for each size the kit meets:
 *
 *  - `options` for a small, known list - webhook events, shipping methods;
 *  - `optionsUrl` for a lazy GET endpoint returning `{ value, label }[]` -
 *    the product/attribute pickers that would otherwise balloon the payload.
 *
 * The value is the raw scalar array, so `product_ids: [1, 4]` needs no
 * `serialize`: the FormRequest validates the array as-is, and the bridge
 * already resolved `optionsUrl` from its route name.
 */
type RelationItem = { value: string | number; label: string };

/**
 * Resolve a field's option list: static `options` when declared, otherwise a
 * lazy GET from `optionsUrl`. Shared by `relation` (multi-value picker),
 * `money` (currency list) and `select` (single-value picker over a lively
 * catalog), so they call the same endpoint shape with the same loading and
 * error copy instead of drifting.
 * same loading and error copy instead of drifting.
 */
function useOptions(field: FieldDefinition): { options: FieldOption[]; loaded: boolean; error: string | undefined } {
    const [options, setOptions] = React.useState<FieldOption[]>(field.options ?? []);
    const [loaded, setLoaded] = React.useState<boolean>(Boolean(field.options));
    const [error, setError] = React.useState<string | undefined>(undefined);

    React.useEffect(() => {
        if (field.options || !field.optionsUrl) {
            return;
        }

        let cancelled = false;

        fetch(field.optionsUrl, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Failed to load options (${response.status})`);
                }

                return response.json();
            })
            .then((body) => {
                if (cancelled) {
                    return;
                }

                const rows = Array.isArray(body) ? body : Array.isArray(body?.options) ? body.options : [];

                setOptions(rows as FieldOption[]);
                setLoaded(true);
            })
            .catch((caught) => {
                if (!cancelled) {
                    setError(caught instanceof Error ? caught.message : "Failed to load options");
                    setLoaded(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [field.options, field.optionsUrl]);

    return { options, loaded, error };
}

function RelationInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    const selected = React.useMemo(() => (Array.isArray(value) ? (value as Array<string | number>) : []), [value]);

    const { options, loaded, error: loadError } = useOptions(field);

    const items = React.useMemo(
        () => options.map((option) => ({ value: option.value, label: option.label }) as RelationItem),
        [options],
    );

    const selectedItems = React.useMemo(
        () => items.filter((item) => selected.some((entry) => String(entry) === String(item.value))),
        [items, selected],
    );

    const anchorRef = useComboboxAnchor();
    const label = field.label ?? humanizeFieldName(name);

    return (
        <div className="space-y-2">
            <Combobox
                items={items}
                multiple
                disabled={disabled}
                value={selectedItems}
                itemToStringLabel={(item) => item.label}
                onValueChange={(next) => setValue((next as RelationItem[]).map((item) => item.value))}
            >
                <div ref={anchorRef}>
                    <ComboboxChips aria-label={label} aria-invalid={loadError ? "true" : undefined}>
                        <ComboboxValue>
                            {(current) => (
                                <>
                                    {((current ?? []) as RelationItem[]).map((item) => (
                                        <ComboboxChip key={String(item.value)} aria-label={item.label}>
                                            {item.label}
                                        </ComboboxChip>
                                    ))}
                                    <ComboboxChipsInput
                                        id={id}
                                        name={name}
                                        disabled={disabled}
                                        placeholder={field.placeholder}
                                        className={selectedItems.length > 0 ? "w-24" : "w-full"}
                                    />
                                </>
                            )}
                        </ComboboxValue>
                    </ComboboxChips>
                </div>
                <ComboboxContent anchor={anchorRef}>
                    <ComboboxList>
                        {(item: RelationItem) => (
                            <ComboboxItem key={String(item.value)} value={item}>
                                <span>{item.label}</span>
                            </ComboboxItem>
                        )}
                    </ComboboxList>
                    <ComboboxEmpty>{loaded ? "No options." : "Loading…"}</ComboboxEmpty>
                </ComboboxContent>
            </Combobox>
            {loadError ? <p className="text-xs text-destructive">{loadError}</p> : null}
        </div>
    );
}

/**
 * `checkboxList` is the same multi-value picker `relation` is, rendered as a
 * scrolling list of tick boxes instead of a searchable combobox. It exists
 * because the two answer different questions: a combobox is right when the
 * catalog is long enough that finding an entry means *searching* for it
 * (products on a coupon), and a list is right when a staff member needs to see
 * the whole available set at once and tick several of it (categories on a
 * product's bulk assign). Shipping the list as its own type rather than a flag
 * on `relation` leaves every existing `relation` field rendering exactly as it
 * did.
 *
 * The value is the raw scalar array, the same shape `relation` writes, so a
 * bulk action can move between the two controls without touching its payload.
 */
function CheckboxListInput({ field, id, disabled, value, setValue }: FieldControlProps) {
    const selected = React.useMemo(() => (Array.isArray(value) ? (value as Array<string | number>) : []), [value]);
    const { options, loaded, error: loadError } = useOptions(field);

    const isSelected = (option: FieldOption) => selected.some((entry) => String(entry) === String(option.value));

    const toggle = (option: FieldOption) => {
        if (disabled) {
            return;
        }

        setValue(
            isSelected(option)
                ? selected.filter((entry) => String(entry) !== String(option.value))
                : [...selected, option.value],
        );
    };

    return (
        <div className="space-y-2">
            <div
                className="max-h-56 overflow-y-auto rounded-md border p-2"
                role="group"
                aria-labelledby={id}
                aria-invalid={loadError ? "true" : undefined}
            >
                {options.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {loaded
                            ? "There is nothing to pick here yet - create one first, then come back."
                            : "Loading…"}
                    </p>
                ) : (
                    <div className="flex flex-col gap-1">
                        {options.map((option) => {
                            const optionId = `${id}-${String(option.value)}`;

                            return (
                                <label
                                    key={String(option.value)}
                                    htmlFor={optionId}
                                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted"
                                >
                                    <Checkbox
                                        id={optionId}
                                        checked={isSelected(option)}
                                        disabled={disabled}
                                        onCheckedChange={() => toggle(option)}
                                    />
                                    <span className="truncate">{option.label}</span>
                                </label>
                            );
                        })}
                    </div>
                )}
            </div>
            <p className="text-xs text-muted-foreground" data-selected-count={selected.length}>
                {selected.length} selected
            </p>
            {loadError ? <p className="text-xs text-destructive">{loadError}</p> : null}
        </div>
    );
}

/**
 * A switch is a boolean, so it stores a boolean. A checkbox storing `"on"`
 * means every consumer downstream has to know which of the two conventions it
 * is holding, and the ones that guess wrong coerce `"off"` to `true`.
 */
function BooleanInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    const label = field.label ?? humanizeFieldName(name);

    return (
        <div className="flex items-center gap-2">
            <Switch id={id} name={name} disabled={disabled} checked={Boolean(value)} onCheckedChange={(checked) => setValue(Boolean(checked))} />
            <span className="text-sm text-muted-foreground">{label}</span>
        </div>
    );
}

function DateInput({ id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <Input
            id={id}
            name={name}
            type="date"
            disabled={disabled}
            value={value == null ? "" : String(value)}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

function DateTimeInput({ id, name, disabled, value, setValue }: FieldControlProps) {
    // `datetime-local` wants `YYYY-MM-DDTHH:mm`; a database hands back
    // `YYYY-MM-DD HH:mm:ss`. Trimming to the first 16 characters handles the
    // space form, and the `T` swap handles the rest.
    const asLocal = value == null ? "" : String(value).replace(" ", "T").slice(0, 16);

    return (
        <Input
            id={id}
            name={name}
            type="datetime-local"
            disabled={disabled}
            value={asLocal}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

/**
 * A slug field shows the live preview a second input produces, because the
 * whole point of editing a slug is watching it change.
 */
function SlugInput({ id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <div className="space-y-2">
            <Input id={id} name={name} disabled={disabled} value={value == null ? "" : String(value)} onChange={(event) => setValue(event.target.value)} />
            <p className="text-xs text-muted-foreground font-mono">/{String(value ?? "")}</p>
        </div>
    );
}

function JsonInput({ id, name, disabled, value, setValue }: FieldControlProps) {
    const [text, setText] = React.useState(() => (value == null ? "" : JSON.stringify(value, null, 2)));
    const [error, setError] = React.useState<string | undefined>(undefined);

    // Validate on change rather than on blur: a JSON textarea has no natural
    // moment to be "done", so the error has to arrive while the cursor is
    // still in it.
    React.useEffect(() => {
        if (text.trim() === "") {
            setError(undefined);
            setValue(null);

            return;
        }

        try {
            setValue(JSON.parse(text));
            setError(undefined);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : "Invalid JSON");
        }
    }, [text, setValue]);

    return (
        <div className="space-y-2">
            <Textarea
                id={id}
                name={name}
                rows={6}
                disabled={disabled}
                className="font-mono text-xs"
                value={text}
                aria-invalid={Boolean(error)}
                onChange={(event) => setText(event.target.value)}
            />
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

/**
 * A `media` field is a picker, not a path text box. The value contract is the
 * URL the picker hands back (single) or a `ServerGalleryImage[]` (multiple);
 * the dropzone, the library dialog, the remove + best-effort delete and the
 * upload progress all come from the shared pickers so a resource form and a
 * hand-written page cannot drift apart in how a media field behaves.
 *
 * Endpoints come from the field declaration (`uploadUrl`/`deleteUrl`/`subdirectory`):
 * a serialized PHP descriptor's route names are resolved by the bridge, and a
 * TS-side resource can pass URLs directly. With no endpoints the control still
 * renders - a descriptor's `media` field is then a library-pick/URL-carry field.
 */
function MediaInput(props: FieldControlProps) {
    if (props.field.multiple) {
        return <MultipleMediaInput {...props} />;
    }

    return <SingleMediaInput {...props} />;
}

function SingleMediaInput({ value, setValue, field }: FieldControlProps) {
    return (
        <FeaturedImageSelector
            value={typeof value === "string" ? value : ""}
            onChange={(url) => setValue(url)}
            uploadUrl={field.uploadUrl}
            deleteUrl={field.deleteUrl}
            subdirectory={field.subdirectory}
            label=""
            description=""
        />
    );
}

function MultipleMediaInput({ value, setValue, field }: FieldControlProps) {
    const images = Array.isArray(value) ? (value as ServerGalleryImage[]) : [];

    return (
        <GalleryUploader
            images={images}
            onChange={(next: ServerGalleryImage[]) => setValue(next)}
            uploadUrl={field.uploadUrl ?? ""}
            deleteUrl={field.deleteUrl ?? ""}
            label=""
            description=""
        />
    );
}

function PasswordInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <Input
            id={id}
            name={name}
            type="password"
            disabled={disabled}
            placeholder={field.placeholder ?? "••••••••"}
            value={value == null ? "" : String(value)}
            onChange={(event) => setValue(event.target.value)}
        />
    );
}

function HiddenInput({ id, name, value }: FieldControlProps) {
    // Rendered as a real input so the browser's own form semantics and any
    // progressive-enhancement path still see the value.
    return <input type="hidden" id={id} name={name} value={value == null ? "" : String(value)} readOnly />;
}

/**
 * Spans are written out rather than interpolated - the same rule as
 * `ResourceForm`: Tailwind scans source text for classes, so a computed
 * `sm:col-span-${span}` produces no CSS and the layout silently collapses.
 */
const SUBFIELD_SPANS: Record<number, string> = {
    1: "",
    2: "sm:col-span-2",
    3: "sm:col-span-3",
    4: "sm:col-span-4",
};

/**
 * A reorderable row list. Every row carries the same sub-fields, each rendered
 * through the same registry this module feeds - so a row can hold a `money`
 * override, a `relation` picker or a `media` gallery just like a top-level
 * field, unlike the shared `Repeater`'s narrower subset.
 *
 * The value is the rows array in display order. Each row is a plain object keyed
 * by sub-field name; a row that came from the server carries an `id`, which is
 * what lets the owning controller diff edited rows against what it persisted
 * (create when absent, update when present, delete when a persisted row is not
 * in the submitted array). Duplicating a row drops its `id`, so a duplicated
 * row arrives as a create instead of a second update against the same record.
 *
 * Sub-field server errors resolve from the `field.0.sub_field` keys a
 * `methods.*.override_price` style rule produces; they land under the exact
 * sub-field that failed.
 */
function NestedInput({ field, id, name, disabled, value, setValue, errors }: FieldControlProps) {
    const rows = React.useMemo(
        () => (Array.isArray(value) ? (value as ResourceRecord[]) : []),
        [value],
    );
    const subFields = field.rows ?? [];
    const label = field.label ?? humanizeFieldName(name);
    const emptyLabel = field.placeholder ?? `No ${label.toLowerCase()} yet.`;

    // Rows are identified by their index, so every mutation has to rebase the
    // open/closed states that travel with an index (reorder, insert, delete).
    // Default-new-rows-open: adding a row should show the fields the user is
    // about to fill, not hide them behind a collapsed header.
    const [openRows, setOpenRows] = React.useState<Record<number, boolean>>({});

    // After an insert at `at`, every index at or past the insertion point came
    // from the row one position earlier, except the new row itself. After a
    // delete at `at`, every index at or past the gap came from the row one
    // position later.
    const openStatesForInsert = (rowsAfter: ResourceRecord[], at: number): Record<number, boolean> => {
        const next: Record<number, boolean> = {};

        for (const index of rowsAfter.keys()) {
            next[index] = index === at ? true : Boolean(openRows[index < at ? index : index - 1]);
        }

        return next;
    };

    const openStatesForRemove = (rowsAfter: ResourceRecord[], at: number): Record<number, boolean> => {
        const next: Record<number, boolean> = {};

        for (const index of rowsAfter.keys()) {
            next[index] = Boolean(openRows[index < at ? index : index + 1]);
        }

        return next;
    };

    const patchRow = (index: number, next: ResourceRecord) =>
        setValue(rows.map((row, i) => (i === index ? next : row)));

    const blankRow = (): ResourceRecord => {
        const blank: ResourceRecord = {};

        for (const sub of subFields) {
            if (sub.name in blank) {
                continue;
            }

            blank[sub.name] = sub.defaultValue;
        }

        return blank;
    };

    const addRow = () => {
        const next = [...rows, blankRow()];

        setValue(next);
        setOpenRows(openStatesForInsert(next, next.length - 1));
    };

    const duplicateRow = (index: number) => {
        const cloned = structuredClone(rows[index] ?? {});
        delete cloned.id;

        const next = [...rows];
        next.splice(index + 1, 0, cloned);

        setValue(next);
        setOpenRows(openStatesForInsert(next, index + 1));
    };

    const removeRow = (index: number) => {
        const next = rows.filter((_, i) => i !== index);

        setValue(next);
        setOpenRows(openStatesForRemove(next, index));
    };

    const reorderRows = (activeIndex: number, overIndex: number) => {
        const next = arrayMove(rows, activeIndex, overIndex);
        const openArray = rows.map((_, i) => Boolean(openRows[i]));
        const movedOpen = arrayMove(openArray, activeIndex, overIndex);

        setValue(next);
        setOpenRows(Object.fromEntries(movedOpen.map((open, i) => [i, open])));
    };

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const handleDragStart = (event: { active: { id: UniqueIdentifier } }) => {
        setActiveId(String(event.active.id));
    };

    const handleDragEnd = (event: { active: { id: UniqueIdentifier }; over: { id: UniqueIdentifier } | null }) => {
        const { active, over } = event;

        setActiveId(null);

        if (!over) {
            return;
        }

        const activeIndex = Number(active.id);
        const overIndex = Number(over.id);

        if (activeIndex !== overIndex) {
            reorderRows(activeIndex, overIndex);
        }
    };

    const [, setActiveId] = React.useState<string | null>(null);

    const itemIds = rows.map((_, index) => String(index));

    return (
        <div className="space-y-3" data-nested={name}>
            <div className="space-y-3 touch-none min-w-0">
                {rows.length > 0 ? (
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragStart={handleDragStart}
                        onDragEnd={handleDragEnd}
                        onDragCancel={() => setActiveId(null)}
                    >
                        <SortableContext items={itemIds} strategy={verticalListSortingStrategy}>
                            {rows.map((row, index) => (
                                <NestedRow
                                    key={index}
                                    id={String(index)}
                                    index={index}
                                    field={field}
                                    row={row}
                                    open={Boolean(openRows[index])}
                                    onToggle={(open) => setOpenRows((prev) => ({ ...prev, [index]: open }))}
                                    onPatchRow={(next) => patchRow(index, next)}
                                    onDuplicate={() => duplicateRow(index)}
                                    onRemove={() => removeRow(index)}
                                    disabled={disabled}
                                    errorPrefix={`${name}.${index}`}
                                    baseId={`${id}-row-${index}`}
                                    errors={errors ?? {}}
                                />
                            ))}
                        </SortableContext>
                    </DndContext>
                ) : null}
            </div>

            <div className="flex items-center justify-between">
                <p className="text-xs text-muted-foreground">
                    {rows.length} {rows.length === 1 ? "row" : "rows"}
                </p>
                <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={addRow}>
                    <Plus className="mr-1 h-4 w-4" />
                    Add {label.toLowerCase()}
                </Button>
            </div>

            {rows.length === 0 ? <p className="text-sm text-muted-foreground">{emptyLabel}</p> : null}
        </div>
    );
}

interface NestedRowProps {
    id: string;
    index: number;
    field: FieldDefinition;
    row: ResourceRecord;
    open: boolean;
    onToggle: (open: boolean) => void;
    onPatchRow: (next: ResourceRecord) => void;
    onDuplicate: () => void;
    onRemove: () => void;
    disabled: boolean;
    errorPrefix: string;
    baseId: string;
    errors: Record<string, string>;
}

function NestedRow({
    id,
    index,
    field,
    row,
    open,
    onToggle,
    onPatchRow,
    onDuplicate,
    onRemove,
    disabled,
    errorPrefix,
    baseId,
    errors,
}: NestedRowProps) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    const style: React.CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        zIndex: isDragging ? 1000 : 1,
        position: "relative" as const,
    };

    const subFields = field.rows ?? [];
    const titleKey = field.rowTitleField ?? subFields[0]?.name;
    const rawTitle = titleKey ? row[titleKey] : undefined;
    const title = rawTitle != null && String(rawTitle).trim() !== "" ? String(rawTitle) : `Item ${index + 1}`;

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`border rounded-lg bg-card min-w-0 overflow-hidden ${isDragging ? "shadow-lg ring-2 ring-primary" : ""}`}
        >
            <Collapsible open={open} onOpenChange={onToggle}>
                <div className="flex items-center gap-2 p-3 min-w-0 overflow-hidden">
                    <button
                        type="button"
                        {...attributes}
                        {...listeners}
                        className="text-muted-foreground cursor-grab hover:text-foreground active:cursor-grabbing touch-none p-1 rounded"
                        title="Drag to reorder"
                        aria-label={`Drag ${title} to reorder`}
                    >
                        <GripVertical className="h-5 w-5" />
                    </button>

                    <CollapsibleTrigger
                        nativeButton={false}
                        render={
                            <div className="flex items-center gap-2 px-2 flex-1 min-w-0 overflow-hidden cursor-pointer select-none" />
                        }
                    >
                        {open ? (
                            <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                        ) : (
                            <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                        )}
                        <div className="min-w-0 flex-1" title={title}>
                            <span className="block font-medium text-foreground text-left truncate">{title}</span>
                        </div>
                    </CollapsibleTrigger>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={disabled}
                        onClick={onDuplicate}
                        className="shrink-0"
                        aria-label={`Duplicate ${title}`}
                    >
                        <Copy className="h-4 w-4" />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={disabled}
                        onClick={onRemove}
                        aria-label={`Remove ${title}`}
                        className="shrink-0 text-destructive hover:text-destructive"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>

                <CollapsibleContent>
                    <div className="px-4 pb-4 pt-3 border-t">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {subFields.map((sub) => (
                                <div
                                    key={sub.name}
                                    className={SUBFIELD_SPANS[sub.colSpan ?? 1] || undefined}
                                >
                                    <ResourceField
                                        idPrefix={baseId}
                                        context={{
                                            field: sub,
                                            record: row,
                                            value: row[sub.name],
                                            error: errors[`${errorPrefix}.${sub.name}`] ?? errors[`${errorPrefix}`],
                                            setValue: (value) => onPatchRow({ ...row, [sub.name]: value }),
                                            setField: (path, value) => onPatchRow({ ...row, [path]: value }),
                                            disabled,
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>
    );
}

const BUILT_INS: Record<Exclude<FieldType, "dynamic">, FieldTypeRegistration> = {
    text: { control: TextInput, initialValue: () => "" },
    textarea: { control: TextareaInput, initialValue: () => "" },
    number: { control: NumberInput, initialValue: () => "" },
    money: { control: MoneyInput, initialValue: () => "" },
    select: { control: SelectInput, initialValue: () => "" },
    boolean: { control: BooleanInput, initialValue: () => false },
    date: { control: DateInput, initialValue: () => "" },
    datetime: { control: DateTimeInput, initialValue: () => "" },
    slug: { control: SlugInput, initialValue: () => "" },
    json: { control: JsonInput, initialValue: () => null },
    media: { control: MediaInput, initialValue: () => "" },
    password: { control: PasswordInput, initialValue: () => "" },
    hidden: { control: HiddenInput },
    relation: { control: RelationInput, initialValue: () => [] },
    checkboxList: { control: CheckboxListInput, initialValue: () => [] },
    nested: { control: NestedInput, initialValue: () => [] },
};

for (const [type, registration] of Object.entries(BUILT_INS)) {
    registerFieldType(type as Exclude<FieldType, "dynamic">, registration);
}

/** Render a single field: label, control, help text, server error. */
export function ResourceField({ context, idPrefix = "resource-field" }: { context: FieldContext; idPrefix?: string }) {
    const { field, error } = context;
    const id = `${idPrefix}-${field.name.replace(/\./g, "-")}`;

    return (
        <div className={field.hidden ? "hidden" : "space-y-2"} data-field={field.name}>
            <Label field={field} id={id} required={field.required} />
            <Control {...context} id={id} name={field.name} disabled={Boolean(context.disabled)} />
            {field.help && !error ? <p className="text-xs text-muted-foreground">{field.help}</p> : null}
            {error ? (
                <p className="text-xs text-destructive" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
