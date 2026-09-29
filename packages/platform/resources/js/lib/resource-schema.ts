import type { ReactNode, ComponentType } from "react";
import type { CellData, ColumnDef } from "@tanstack/react-table";
import type { BulkAction } from "../Components/ui/data-table";
import type { AppTableFeatures } from "./table-features";

/**
 * A declarative description of an admin CRUD resource.
 *
 * The point of this file is that an admin screen should be *data* - a list of
 * columns, a list of fields, a list of relations - and not a component. Every
 * admin page in the platform and in its consumers re-implements the same
 * twenty lines: a row interface, a `columns` literal, a `useState` for the
 * delete target, a `<DataTable>` with four props wired to the same names, and
 * `router.delete()` with a toast. That is the boilerplate `ResourceIndex` and
 * `ResourceForm` exist to delete, and this module is the shape they read.
 *
 * It is a schema, not an ORM. Nothing here talks to a database or infers
 * anything: a resource is declared by hand, and the runtime renders exactly
 * what was declared. Where a declaration is wrong the page is wrong in an
 * obvious place, which is the failure mode we want - a framework that guesses
 * wrong is worse than no framework.
 *
 * Scope boundary: this describes *rendering*. Persistence stays with Inertia
 * `router` calls and the app's own controllers, exactly as it is for the
 * hand-written pages today. There is no generic store, no autosave, and no
 * attempt to become a query layer.
 */

/** The record shape a resource operates on. Indexes are read, forms are written. */
export type ResourceRecord = Record<string, unknown>;

/**
 * Field types. Every one of these except `dynamic` has a built-in control
 * registered in `Components/resources/fields`. `dynamic` defers to the field's
 * own `render` and exists so a resource can carry a bespoke control without
 * the consuming app having to register a type under its own name first.
 */
export type FieldType =
    | "text"
    | "textarea"
    | "number"
    | "money"
    | "select"
    | "boolean"
    | "date"
    | "datetime"
    | "slug"
    | "json"
    | "media"
    | "password"
    | "hidden"
    | "relation"
    | "dynamic";

export interface FieldOption {
    label: string;
    value: string | number;
}

/** Everything a control needs to render itself, supplied by the runtime. */
export interface FieldContext {
    /** Current form value, typed as unknown because the schema is untyped data. */
    value: unknown;
    /** Assign a new value to this field. Called with whatever the control produced. */
    setValue: (value: unknown) => void;
    /**
     * Write any key in the form, not just this field's. Needed by controls that
     * own more than one value - `money` keeps a currency beside its amount.
     */
    setField: (path: string, value: unknown) => void;
    /** Server validation message for this field, if any. */
    error?: string;
    disabled?: boolean;
    /** The whole record, for `visibleWhen` and custom renderers. */
    record: ResourceRecord;
    field: FieldDefinition;
}

export interface FieldDefinition {
    /** Form/column key. Dotted paths (`shipping.address.city`) are read, not written. */
    name: string;
    type: FieldType;
    /** Human label. Falls back to a title-cased `name`. */
    label?: string;
    required?: boolean;
    /** Helper text under the control. */
    help?: string;
    placeholder?: string;
    options?: FieldOption[];
    /**
     * Static or lazy option source for `relation` fields (and the currency
     * list on `money`). A serialized PHP descriptor always passes a route
     * *name* here; `toResource()` resolves it against Ziggy, so the runtime
     * definition holds a URL. A TS-side resource may set a URL directly. GET
     * must return `{ value, label }[]`.
     */
    optionsUrl?: string;
    /**
     * Upload endpoint for the `media` control, as a route name (serialized
     * descriptors) or a URL (TS-side resources). `toResource()` resolves names
     * against Ziggy. POST the file as `file`; expect `{ url, path }`.
     */
    uploadUrl?: string;
    /** Delete endpoint for the `media` control. DELETE `{ path }`; best-effort. */
    deleteUrl?: string;
    /** Media-library folder the picker's "From Library" dialog browses. */
    subdirectory?: string;
    /** Render a multi-image gallery (`GalleryUploader`) instead of a single picker. */
    multiple?: boolean;
    defaultValue?: unknown;
    /** Grid span within a section. 1-4; defaults to 1. */
    colSpan?: 1 | 2 | 3 | 4;
    /** Hidden fields are rendered but not shown - used for ids and tokens. */
    hidden?: boolean;
    /** Conditional visibility, re-evaluated on every render. */
    visibleWhen?: (record: ResourceRecord) => boolean;
    /**
     * Escape hatch for a type the registry does not cover. Preferred over
     * registering a global field type, because it keeps the exception next to
     * the field that needs it.
     */
    render?: (context: FieldContext) => ReactNode;
    /**
     * Outbound coercion, applied on submit. Use for values the control cannot
     * express directly - trimming, blank-to-null, string-to-number.
     */
    serialize?: (value: unknown) => unknown;
}

export interface FieldControlProps extends FieldContext {
    id: string;
    name: string;
    disabled: boolean;
}

/** A column in a resource's index. Thin wrapper over TanStack's `ColumnDef`. */
export interface ResourceColumn<TData extends ResourceRecord = ResourceRecord> {
    /** Accessor key, or a custom `cell` when the value is not directly on the row. */
    name?: keyof TData & string;
    header?: string;
    /** Cell renderer. Receives the row; `value` is the column's resolved value. */
    cell?: (row: TData, value: unknown) => ReactNode;
    /** CSS class applied to both header and body cells (TanStack v9 has no `className`). */
    className?: string;
    sortable?: boolean;
    /** Whether the column appears in the toolbar's view dropdown. */
    hideable?: boolean;
    /** Right-aligned, for money and counts. */
    align?: "left" | "right" | "center";
}

export interface ResourceFilter {
    key: string;
    title?: string;
    options: { label: string; value: string }[];
}

export interface ResourceRowAction<TData extends ResourceRecord = ResourceRecord> {
    label: string;
    /** Resolve the Inertia route for a row, e.g. (row) => `admin/products/${row.id}/edit`. */
    route?: (row: TData) => string;
    /** HTTP verb the route resolves to. Non-`get` actions submit, they do not navigate. */
    method?: "get" | "post" | "put" | "delete";
    /** Destructive actions get a confirm dialog and destructive styling. */
    destructive?: boolean;
    /**
     * Confirm-dialog message for non-destructive actions that deserve a second
     * look (approve, publish, regenerate). Opens the same dialog `destructive`
     * does, with the action's own copy instead of "This cannot be undone."
     */
    confirm?: string;
    /** Serialized PHP descriptors declare this instead of `route`; `toResource()` resolves it. */
    routeName?: string;
    /** Show this action at all - lets a row gate its own menu. */
    show?: (row: TData) => boolean;
    icon?: ComponentType<{ className?: string }>;
}

export interface ResourceTable<TData extends ResourceRecord = ResourceRecord> {
    columns: ResourceColumn<TData>[];
    /** Column the toolbar search box filters on. */
    searchKey?: string;
    /**
     * Multi-column toolbar search: rows matching *any* listed key are kept.
     * Where `searchKey` is a single column filter, `searchKeys` ORs the terms
     * across the listed columns, which a single column filter cannot do.
     */
    searchKeys?: string[];
    searchPlaceholder?: string;
    filters?: ResourceFilter[];
    bulkActions?: BulkAction[];
    /** Row dropdown, appended after edit/delete. */
    rowActions?: ResourceRowAction<TData>[];
    defaultPageSize?: number;
    pageSizeOptions?: number[];
    emptyState?: ReactNode;
    loading?: boolean;
}

export interface ResourceSection {
    title?: string;
    description?: string;
    fields: FieldDefinition[];
}

/**
 * A child table belonging to a resource - the generalisation of the 1,491-line
 * `VariationsManager`, reduced to a declaration.
 *
 * Deliberately excludes drag-reorder: that is a `@dnd-kit` concern specific to
 * ordered children, and folding it in here would make every relation pay for
 * it. A relation that is ordered declares its own reorder handler; the table
 * below does the parts every relation shares.
 */
export interface ResourceRelation {
    /** React component rendered inside the parent's form. */
    component: ComponentType<RelationProps>;
    /** Stable key, used for the section heading and for tests. */
    key: string;
    title?: string;
    description?: string;
}

export interface RelationProps {
    /** The parent record being edited. */
    record: ResourceRecord;
    /** Errors keyed the same way the parent's are. */
    errors: Record<string, string>;
}

export interface ResourceDefinition<TData extends ResourceRecord = ResourceRecord> {
    /** Unique key, e.g. "shop/products". Used for testids and registry lookups. */
    key: string;
    label: string;
    /** Singular label, used in headings and toasts. */
    singular?: string;
    table?: ResourceTable<TData>;
    /** Form sections, in render order. */
    form?: ResourceSection[];
    relations?: ResourceRelation[];
    /** Inertia route helpers, kept on the definition so a page declares them once. */
    routes?: {
        index?: string;
        create?: string;
        store?: string;
        edit?: (row: TData) => string;
        update?: (row: TData) => string;
        destroy?: (row: TData) => string;
    };
}

/**
 * Identity function whose real job is the generic parameterisation. Without an
 * explicit annotation a literal's `cell` callbacks widen to `ResourceRecord`
 * and every call site loses its row type, which is the whole reason a resource
 * would declare one.
 */
export function defineResource<TData extends ResourceRecord = ResourceRecord>(
    definition: ResourceDefinition<TData>,
): ResourceDefinition<TData> {
    return definition;
}

/** Declare a relation table. See `ResourceRelation` for the ordering caveat. */
export function relation(definition: ResourceRelation): ResourceRelation {
    return definition;
}

/**
 * Translate a declarative column into the TanStack `ColumnDef` `DataTable`
 * expects. Kept here rather than in the component so the translation is
 * testable without rendering a table.
 */
export function toColumnDef<TData extends ResourceRecord>(column: ResourceColumn<TData>): ColumnDef<AppTableFeatures, TData, CellData> {
    const alignment =
        column.align === "right" ? "text-right" : column.align === "center" ? "text-center" : "text-left";

    return {
        id: column.name,
        accessorKey: column.name,
        header: column.header,
        enableSorting: column.sortable ?? true,
        enableHiding: column.hideable ?? true,
        cell: column.cell ? (context: { row: { original: TData } }) => column.cell!(context.row.original, (context.row.original as ResourceRecord)[column.name as string]) : undefined,
        meta: {
            className: [alignment, column.className].filter(Boolean).join(" "),
        },
    } as ColumnDef<AppTableFeatures, TData, CellData>;
}

/** Flatten sections into the single field list a control lookup needs. */
export function flattenFields(sections: ResourceSection[] | undefined): FieldDefinition[] {
    return (sections ?? []).flatMap((section) => section.fields);
}

/** "created_at" -> "Created at", for fields that declare no label. */
export function humanizeFieldName(name: string): string {
    const tail = name.split(".").pop() ?? name;

    return tail
        .replace(/[_-]+/g, " ")
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/^./, (character) => character.toUpperCase());
}

/**
 * Read a possibly-dotted path off a record. Dotted names are read-only: the
 * runtime writes flat keys, because a form that can silently create
 * `a.b.c` on submit is a form that can silently drop data.
 */
export function readPath(record: ResourceRecord, path: string): unknown {
    return path.split(".").reduce<unknown>((value, segment) => {
        if (value && typeof value === "object") {
            return (value as ResourceRecord)[segment];
        }

        return undefined;
    }, record);
}

/** Whether a field should render, honouring `visibleWhen` and `hidden`. */
export function isFieldVisible(field: FieldDefinition, record: ResourceRecord): boolean {
    if (field.hidden) {
        return true;
    }

    return field.visibleWhen ? field.visibleWhen(record) : true;
}

/*
 * ─── Serialized descriptor bridge (kernel §5.2) ───────────────────────────
 *
 * A PHP `ResourceDefinition` (`src/Support/ResourceDefinition.php`) serializes
 * to `SerializedResource` and arrives as an Inertia page prop. `toResource()`
 * rehydrates it into the runtime `ResourceDefinition` the generic
 * `dashboard/admin/resources/{index,form}` pages render.
 *
 * The bridge is lossy on purpose:
 *  - `relations` are dropped — a PHP descriptor can never carry a React
 *    component, so a serialized "relation" is a stub, not a row of the
 *    VariationsManager. Live side-car tables stay in the owning feature's own
 *    page (or an `extra` prop its controller passes next to the descriptor).
 *  - `edit`/`update`/`destroy` become URL builders bound to the literal
 *    `record` route param (matching `ResourceRenderer::RECORD_PARAM`), because
 *    the PHP side declares only route *names*.
 */

/** The exact shape `ResourceDefinition::toArray()` (and `fromArray`) emits. */
export interface SerializedResource {
    key: string;
    label: string;
    singular?: string | null;
    table?: Record<string, unknown>;
    form?: Array<Record<string, unknown>>;
    relations?: Array<Record<string, unknown>>;
    routes?: Partial<Record<RouteSlot, string | null>>;
    permissions?: Partial<Record<"view" | "create" | "edit" | "delete", string | null>>;
}

type RouteSlot = "index" | "create" | "store" | "edit" | "update" | "destroy";

/** Literal route parameter the generic pages bind for record-scoped URLs. */
export const RESOURCE_RECORD_PARAM = "record";

type RouteRef = (name: string, params?: Record<string, unknown>) => string;

/**
 * Resolve a route name a serialized descriptor passes for a URL surface
 * (`optionsUrl`, `uploadUrl`, `deleteUrl`) into a fetchable URL. Values that
 * already are URLs pass through untouched; an empty or absent value stays
 * absent.
 */
function resolveSerializedUrl(
    value: unknown,
    routeRef: RouteRef,
): string | undefined {
    if (typeof value !== "string" || value === "") {
        return undefined;
    }

    if (value.startsWith("/") || /^https?:\/\//i.test(value)) {
        return value;
    }

    return resolveResourceRoute(value, undefined, routeRef) ?? value;
}

/**
 * Whether a row matches a toolbar search across any of the listed keys.
 *
 * This backs `ResourceTable.searchKeys`: a single column filter would AND the
 * term across columns, but a search box asking for a name *or* an email wants
 * OR semantics. `readPath` is reused so dotted accessor keys work identically
 * on index + toolbar here and on the form.
 */
export function rowMatchesSearchKeys(row: ResourceRecord, query: string, keys: string[]): boolean {
    const needle = query.trim().toLowerCase();

    if (needle === "" || keys.length === 0) {
        return true;
    }

    return keys.some((key) => {
        const value = readPath(row, key);

        return value != null && String(value).toLowerCase().includes(needle);
    });
}

/**
 * Rehydrate a serialized PHP descriptor into a runtime `ResourceDefinition`.
 * The default `routeRef` is Ziggy's global `route`; tests inject a spy.
 *
 * Besides the literal `record` route slots, the bridge resolves two
 * route-name surfaces the PHP side cannot express as closures but the runtime
 * needs as URLs:
 *  - `table.rowActions` entries declare `routeName`; each becomes a `route`
 *    builder bound to `{ record: row.id }` (interim Route URL) carrying the
 *    declared verb (`method`) and optional `confirm` copy.
 *  - field `optionsUrl` (the `relation` field's lazy option source) is
 *    resolved from a route name to a URL.
 */
export function toResource(
    serialized: SerializedResource,
    routeRef: RouteRef = (name, params) => route(name, params),
): ResourceDefinition {
    const routes = serialized.routes ?? {};

    const recordUrl = (name: string | null | undefined): ((row: ResourceRecord) => string) | undefined => {
        return name && name !== "" ? (row) => routeRef(name, { [RESOURCE_RECORD_PARAM]: row.id }) : undefined;
    };

    const table = serialized.table ?? undefined;

    return {
        key: serialized.key,
        label: serialized.label,
        singular: serialized.singular ?? undefined,
        table: table
            ? ({ ...table, rowActions: mapSerializedRowActions(table.rowActions, routeRef) } as ResourceTable<ResourceRecord>)
            : undefined,
        form: (serialized.form ?? undefined)?.map(
            (section) =>
                ({
                    ...section,
                    // Relation lazy option sources come through as route names; the
                    // runtime reads `optionsUrl` as a fetchable URL. A value that
                    // already is a URL is left alone - the bridge only resolves names.
                    fields: ((section.fields ?? []) as Array<Record<string, unknown>>).map((field) => {
                        const resolved: Record<string, unknown> = { ...field };

                        // `optionsUrl`, `uploadUrl` and `deleteUrl` arrive as
                        // route names from a PHP descriptor; the runtime needs
                        // URLs. Resolve each declared surface, leaving keys the
                        // field did not declare absent.
                        for (const key of ["optionsUrl", "uploadUrl", "deleteUrl"] as const) {
                            const url = resolveSerializedUrl(field[key], routeRef);

                            if (url === undefined) {
                                delete resolved[key];
                            } else {
                                resolved[key] = url;
                            }
                        }

                        return resolved as unknown as FieldDefinition;
                    }),
                }) as ResourceSection,
        ),
        routes: {
            index: routes.index ?? undefined,
            create: routes.create ?? undefined,
            store: routes.store ?? undefined,
            edit: recordUrl(routes.edit),
            update: recordUrl(routes.update),
            destroy: recordUrl(routes.destroy),
        },
    };
}

/** Row-action shape a serialized PHP descriptor emits for `table.rowActions`. */
interface SerializedRowAction {
    label: string;
    routeName?: string;
    method?: "get" | "post" | "put" | "delete";
    destructive?: boolean;
    confirm?: string;
}

/**
 * Turn `routeName` row actions into runtime `route` builders bound to the
 * `record` param. Actions that already carry a `route` (TS-declared) pass
 * through untouched, so `toResource` is safe against both shapes.
 */
export function mapSerializedRowActions(
    rowActions: unknown,
    routeRef: RouteRef = (name, params) => route(name, params),
): ResourceRowAction[] | undefined {
    if (!Array.isArray(rowActions)) {
        return undefined;
    }

    return rowActions.map((declared) => {
        const action = declared as Partial<SerializedRowAction>;

        if (typeof action.routeName === "string" && action.routeName !== "") {
            const name = action.routeName;
            const routeBuilder: (row: ResourceRecord) => string = (row) =>
                routeRef(name, { [RESOURCE_RECORD_PARAM]: row.id });

            return {
                label: action.label ?? "",
                route: routeBuilder,
                method: action.method ?? "get",
                ...(action.destructive !== undefined ? { destructive: action.destructive } : {}),
                ...(typeof action.confirm === "string" && action.confirm !== "" ? { confirm: action.confirm } : {}),
            };
        }

        return action as ResourceRowAction;
    });
}

/** Build an admin URL from a declared route name, or `undefined` when unknown. */
export function resolveResourceRoute(
    name: string | null | undefined,
    params?: Record<string, unknown>,
    routeRef: RouteRef = (planned, plannedParams) => route(planned, plannedParams),
): string | undefined {
    if (!name || name === "") {
        return undefined;
    }

    try {
        return routeRef(name, params);
    } catch {
        return undefined;
    }
}
