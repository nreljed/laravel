import * as React from "react";
import { Input } from "../ui/input";
import { Textarea } from "../ui/textarea";
import { NativeSelect, NativeSelectOption } from "../ui/native-select";
import { Switch } from "../ui/switch";
import { humanizeFieldName, type FieldControlProps, type FieldContext, type FieldDefinition, type FieldType } from "../../lib/resource-schema";

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

function Control({ field, value, record, setValue, setField, id, name, disabled }: FieldControlProps) {


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

    return <Control_ {...{ field, value, record, setValue, setField }} id={id} name={name} disabled={disabled} />;
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
 */
function MoneyInput({ field, id, name, disabled, value, setValue, setField, record }: FieldControlProps) {
    const currency = (record[`${name}_currency`] as string | undefined) ?? "";
    const label = field.label ?? humanizeFieldName(name);

    return (
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
            <Input
                aria-label={`${label} currency`}
                name={`${name}_currency`}
                disabled={disabled}
                className="w-24"
                placeholder="EUR"
                value={currency}
                onChange={(event) => setField(`${name}_currency`, event.target.value)}
            />
        </div>
    );
}

function SelectInput({ field, id, name, disabled, value, setValue }: FieldControlProps) {
    return (
        <NativeSelect
            id={id}
            name={name}
            disabled={disabled}
            value={value == null ? "" : String(value)}
            onChange={(event) => setValue(event.target.value)}
        >
            {field.placeholder ? (
                <NativeSelectOption value="">{field.placeholder}</NativeSelectOption>
            ) : null}
            {(field.options ?? []).map((option) => (
                <NativeSelectOption key={String(option.value)} value={String(option.value)}>
                    {option.label}
                </NativeSelectOption>
            ))}
        </NativeSelect>
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

function MediaInput({ id, name, disabled, value, setValue }: FieldControlProps) {
    const [preview, setPreview] = React.useState<string | null>(typeof value === "string" && value ? value : null);

    return (
        <div className="space-y-2">
            {preview ? <img src={preview} alt="" className="h-32 w-auto rounded border object-cover" /> : null}
            <Input
                id={id}
                name={name}
                disabled={disabled}
                value={value == null ? "" : String(value)}
                placeholder="Media path or URL"
                onChange={(event) => {
                    setValue(event.target.value);
                    setPreview(event.target.value || null);
                }}
            />
        </div>
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
