import * as React from "react";
import { Link, router } from "@inertiajs/react";
import { DataTable } from "../ui/data-table";
import { Button } from "../ui/button";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "../ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "../ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "../ui/dropdown-menu";
import { MoreHorizontal } from "lucide-react";
import { ResourceField, fieldTypeInitialValue } from "./fields";
import { readPath, toColumnDef, type FieldDefinition, type ResourceBulkAction, type ResourceDefinition, type ResourceRecord, type ResourceRowAction } from "../../lib/resource-schema";

/**
 * Renders a resource's index from its declaration.
 *
 * The boilerplate this removes is genuinely mechanical, which is why removing
 * it is safe: a row interface, a `columns` literal, `useState` for the delete
 * target, a `<DataTable>` with eight props under the same names, and a
 * `router.delete()` that toasts. Each of those was retyped per page, and a
 * page that forgot the confirm dialog on a destructive row action was a real
 * bug rather than a hypothetical one.
 *
 * What it deliberately does not do is own the data. `data` is passed in, and
 * every mutation goes back out through `onRowAction` / `onBulkAction` so the
 * page keeps Inertia's `router` and its own error handling. A resource
 * component that owned persistence would be one more place where a redirect
 * policy lives, and redirect policy is the one thing that legitimately differs
 * between two tables of the same shape.
 */
export interface ResourceIndexProps<TData extends ResourceRecord = ResourceRecord> {
    resource: ResourceDefinition<TData>;
    data: TData[];
    /** Create URL. Omit to hide the button. */
    createRoute?: string;
    /** Edit URL builder. Omit to hide the edit action. */
    editRoute?: (row: TData) => string;
    /** Delete URL builder. Omit to hide the delete action entirely. */
    destroyRoute?: (row: TData) => string;
    onRowAction?: (action: ResourceRowAction<TData>, row: TData) => void;
    onBulkAction?: (action: string, ids: (number | string)[]) => void;
    /**
     * Submit a bulk action that collected input. The dialog is rendered here
     * from the action's declared `fields`; the page still owns the request,
     * because it owns the verb, the payload envelope and the error handling.
     */
    onBulkSubmit?: (action: string, ids: (number | string)[], values: Record<string, unknown>) => void;
    /** Send a header action that needs no input (a GET export, a POST sync). */
    onHeaderAction?: (action: { label: string; route: string; method: "get" | "post"; confirm?: string }) => void;
    /** Send a header action carrying a file (a CSV import). */
    onHeaderUpload?: (action: { label: string; route: string; file: File }) => void;
}

export function ResourceIndex<TData extends ResourceRecord = ResourceRecord>({
    resource,
    data,
    createRoute,
    editRoute,
    destroyRoute,
    onRowAction,
    onBulkAction,
    onBulkSubmit,
    onHeaderAction,
    onHeaderUpload,
}: ResourceIndexProps<TData>) {
    const table = resource.table;
    const [confirming, setConfirming] = React.useState<{ title: string; description?: string; confirmLabel: string; run: () => void } | null>(null);
    /* The dialog carries the *declared* action, not the table's `BulkAction`: the
 * `fields`, `dialogTitle` and confirm copy a dialog needs all live on the
 * resource-level shape, and `ResourceBulkAction` is a superset of what the table
 * hands back. */
const [bulkDialog, setBulkDialog] = React.useState<{ action: ResourceBulkAction; ids: (number | string)[]; values: Record<string, unknown> } | null>(null);
    const fileInputs = React.useRef(new Map<string, HTMLInputElement | null>());

    const columns = React.useMemo(() => (table?.columns ?? []).map(toColumnDef), [table?.columns]);

    const runAction = React.useCallback(
        (action: ResourceRowAction<TData>, row: TData) => {
            const execute = () => {
                // The consuming page gets first refusal, because it is the only
                // layer that knows whether this action is a synced mutation, a
                // modal, or a raw Inertia call. A resource that guessed would
                // be wrong about the first resource that needed a different
                // verb.
                if (onRowAction) {
                    onRowAction(action, row);

                    return;
                }

                const method = action.method?.toLowerCase();

                // The page-owned delete route stays the source of truth for the
                // canonical destroy; a declared action carrying `destructive`
                // without a destroyRoute prop still runs through its own verb.
                if (action.destructive && destroyRoute && (method === undefined || method === "delete")) {
                    router.delete(destroyRoute(row), { preserveScroll: true });

                    return;
                }

                if (action.route) {
                    const url = action.route(row);
                    const options = { preserveScroll: true };

                    if (method === "post") {
                        router.post(url, {}, options);
                    } else if (method === "patch") {
                        /* PATCH is not PUT: a feature that routes a partial
                         * update to PATCH answers 405 to a PUT, so the declared
                         * verb is the verb that goes on the wire. */
                        router.patch(url, {}, options);
                    } else if (method === "put") {
                        router.put(url, {}, options);
                    } else if (method === "delete") {
                        router.delete(url, options);
                    } else {
                        router.get(url, options);
                    }
                }
            };

            if (action.destructive) {
                setConfirming({
                    title: `Delete this ${resource.singular ?? resource.label.toLowerCase().replace(/s$/, "")}?`,
                    description: "This cannot be undone.",
                    confirmLabel: "Delete",
                    run: execute,
                });
            } else if (action.confirm) {
                // A confirmed action is not a destructive one: the copy comes
                // from the declaration ("Approve this review?") because there is
                // nothing "cannot be undone"-shaped to warn about.
                setConfirming({
                    title: action.confirm,
                    confirmLabel: action.label,
                    run: execute,
                });
            } else {
                execute();
            }
        },
        [destroyRoute, onRowAction, resource.label, resource.singular],
    );

    const actionsColumn = React.useMemo(() => {
        const declared = table?.rowActions ?? [];
        const all: ResourceRowAction<TData>[] = [...declared];

        if (editRoute) {
            all.unshift({ label: "Edit", route: editRoute });
        }

        if (destroyRoute) {
            all.push({ label: "Delete", route: destroyRoute, destructive: true });
        }

        // No declared actions and no routes means this is a read-only table.
        // Rendering an empty dropdown would be worse than rendering nothing.
        if (all.length === 0) {
            return null;
        }

        return toColumnDef<TData>({
            name: "_actions",
            header: "",
            sortable: false,
            hideable: false,
            className: "w-12",
            cell: (row) => {
                const visible = all.filter((action) => !action.show || action.show(row));

                if (visible.length === 0) {
                    return null;
                }

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="ghost" size="icon" aria-label={`Actions for row ${String(row.id ?? "")}`} />}>
                            <MoreHorizontal className="h-4 w-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {visible.map((action) => {
                                const Icon = action.icon;
                                const label = (
                                    <>
                                        {Icon ? <Icon className="h-4 w-4" /> : null}
                                        {action.label}
                                    </>
                                );

                                // A non-destructive GET action is navigation, so
                                // the whole row is the link. Leaving it to
                                // `onSelect` instead meant clicking the item's
                                // padding did nothing while clicking its text
                                // navigated - the two halves of one item
                                // behaving differently, which reads as a bug. A
                                // POST/PUT/DELETE action is not navigation and
                                // always goes through `runAction`.
                                const isGet = !action.method || action.method === "get";
                                if (action.route && !action.destructive && isGet) {
                                    return (
                                        <DropdownMenuItem
                                            key={action.label}
                                            render={<Link href={action.route(row)} />}
                                        >
                                            {label}
                                        </DropdownMenuItem>
                                    );
                                }

                                return (
                                    <DropdownMenuItem
                                        key={action.label}
                                        className={action.destructive ? "text-destructive" : undefined}
                                        onSelect={(event) => {
                                            // A select handler that then navigates has
                                            // to stop the menu's own dismissal from
                                            // racing the Inertia visit.
                                            event.preventDefault();
                                            runAction(action, row);
                                        }}
                                    >
                                        {label}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        });
    }, [table?.rowActions, editRoute, destroyRoute, runAction]);

    const allColumns = React.useMemo(() => {
        const next = [...columns];

        // The action cell is pinned last, and marked unsortable, so it cannot
        // end up first after a header click.
        if (actionsColumn) {
            next.push({ ...actionsColumn, enableSorting: false });
        }

        return next;
    }, [columns, actionsColumn]);

    /**
     * Seed a bulk action's dialog from its own field defaults, the way
     * `ResourceForm` seeds a create form. Without it a field declaring
     * `defaultValue: "simple"` opens the dialog *empty* and the action's value
     * depends on the user noticing an unselected control - which for a
     * `required` field is indistinguishable from a broken dialog.
     */
    const seedBulkValues = React.useCallback((action: ResourceBulkAction): Record<string, unknown> => {
        const seeded: Record<string, unknown> = {};

        for (const field of action.fields ?? []) {
            if (field.defaultValue !== undefined) {
                seeded[field.name] = field.defaultValue;
            } else {
                seeded[field.name] = fieldTypeInitialValue(field.type);
            }
        }

        return seeded;
    }, []);

    const setBulkValue = React.useCallback((path: string, value: unknown) => {
        setBulkDialog((current) =>
            current ? { ...current, values: { ...current.values, [path]: value } } : current,
        );
    }, []);

    const closeBulkDialog = React.useCallback(() => {
        setBulkDialog(null);
    }, []);

    /**
     * Whether every `required` field in the open dialog holds a value. The Apply
     * button reads this so the dialog cannot submit an action the server is
     * going to reject on `required_if` anyway - the user gets a disabled button
     * instead of a round-trip that ends in a validation error, and the
     * controller stays the single source of truth for what is required.
     */
    const bulkDialogReady = React.useMemo(() => {
        if (!bulkDialog) {
            return false;
        }

        return (bulkDialog.action.fields ?? []).every((field: FieldDefinition) => {
            if (!field.required) {
                return true;
            }

            const value = readPath(bulkDialog.values, field.name);

            if (Array.isArray(value)) {
                return value.length > 0;
            }

            return value !== undefined && value !== null && value !== "";
        });
    }, [bulkDialog]);

    const submitBulkDialog = React.useCallback(() => {
        if (!bulkDialog || !bulkDialogReady) {
            return;
        }

        const { action, ids, values } = bulkDialog;

        // A `destructive` action that also needs input confirms after the
        // fields are filled in, not before - the user has to see what they are
        // about to apply before agreeing to it.
        if (action.destructive) {
            setConfirming({
                title: action.confirmTitle ?? `Confirm ${action.label}`,
                description:
                    action.confirmDescription ??
                    `Apply "${action.label}" to ${ids.length} selected item(s)?`,
                confirmLabel: action.confirmLabel ?? action.label,
                run: () => {
                    onBulkSubmit?.(action.value, ids, values);
                    closeBulkDialog();
                },
            });
            closeBulkDialog();

            return;
        }

        onBulkSubmit?.(action.value, ids, values);
        closeBulkDialog();
    }, [bulkDialog, bulkDialogReady, onBulkSubmit, closeBulkDialog]);

    /**
     * Send a header action. A `fileInput` action is dispatched from the change
     * handler instead, because the browser only hands us the file once the
     * picker closes.
     */
    const runHeaderAction = React.useCallback(
        (action: { label: string; route: string; method?: "get" | "post"; confirm?: string }) => {
            if (!onHeaderAction) {
                return;
            }

            const send = () =>
                onHeaderAction({
                    label: action.label,
                    route: action.route,
                    method: action.method ?? "get",
                    ...(action.confirm ? { confirm: action.confirm } : {}),
                });

            if (action.confirm) {
                setConfirming({
                    title: action.label,
                    description: action.confirm,
                    confirmLabel: action.label,
                    run: send,
                });

                return;
            }

            send();
        },
        [onHeaderAction],
    );

    // Thrown after every hook has run, so a resource that lost its table
    // declaration cannot change the hook count between renders.
    if (!table) {
        throw new Error(`Resource "${resource.key}" has no table declaration; ResourceIndex cannot render it.`);
    }

    return (
        <>
            <div className="flex items-center justify-between gap-4 pb-4">
                <h1 className="text-2xl font-semibold tracking-tight">{resource.label}</h1>
                <div className="flex items-center gap-2">
                    {/* Header actions are the table's own verbs - export, import,
                        sync - so they sit beside Create rather than inside a row. */}
                    {table.headerActions?.map((action) => {
                        if (!action.route) {
                            return null;
                        }

                        if (action.fileInput) {
                            const Icon = (action as { icon?: React.ComponentType<{ className?: string }> }).icon;
                            return (
                                <React.Fragment key={action.label}>
                                    <input
                                        ref={(element) => {
                                            fileInputs.current.set(action.label, element);
                                        }}
                                        type="file"
                                        accept={action.accept}
                                        className="hidden"
                                        aria-label={`${action.label} file`}
                                        onChange={(event) => {
                                            const file = event.target.files?.[0];
                                            // Reset first: re-picking the same file
                                            // after a failed import must still fire.
                                            event.target.value = "";

                                            if (file) {
                                                onHeaderUpload?.({ label: action.label, route: action.route as string, file });
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => fileInputs.current.get(action.label)?.click()}
                                    >
                                        {Icon ? <Icon className="mr-1 h-4 w-4" /> : null}
                                        {action.label}
                                    </Button>
                                </React.Fragment>
                            );
                        }

                        const Icon = (action as { icon?: React.ComponentType<{ className?: string }> }).icon;
                        return (
                            <Button
                                key={action.label}
                                type="button"
                                variant="outline"
                                onClick={() => runHeaderAction(action as { label: string; route: string })}
                            >
                                {Icon ? <Icon className="mr-1 h-4 w-4" /> : null}
                                {action.label}
                            </Button>
                        );
                    })}
                    {createRoute ? (
                        <Button render={<Link href={createRoute} />}>New {resource.singular ?? "item"}</Button>
                    ) : null}
                </div>
            </div>

            <DataTable
                columns={allColumns}
                data={data}
                searchKey={table.searchKey}
                searchKeys={table.searchKeys}
                searchPlaceholder={table.searchPlaceholder}
                filters={table.filters?.map((filter) => ({ ...filter }))}
                bulkActions={table.bulkActions?.map((action) => ({
                    ...action,
                    needsInput: action.needsInput ?? (action.fields?.length ?? 0) > 0,
                }))}
                onBulkAction={onBulkAction}
                onBulkInput={
                    onBulkSubmit
                        ? (action, ids) =>
                              setBulkDialog({
                                  /* The table types the action as its own narrower
                                   * `BulkAction`; it is the very object
                                   * `table.bulkActions` handed it, so the declared
                                   * fields are present at runtime. */
                                  action: action as ResourceBulkAction,
                                  ids,
                                  values: seedBulkValues(action as ResourceBulkAction),
                              })
                        : undefined
                }
                defaultPageSize={table.defaultPageSize}
                pageSizeOptions={table.pageSizeOptions}
                emptyState={table.emptyState}
                loading={table.loading}
            />

            {/* The input a bulk action needs, rendered from its own declared
                fields so a descriptor can describe "assign categories" without
                the page hand-building a form per action. */}
            <Dialog
                open={bulkDialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeBulkDialog();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        {/* A menu label ending in `...` is announcing that a
                            dialog opens; repeating it as the dialog's own
                            title reads as though the dialog is still pending. */}
                        <DialogTitle>
                            {bulkDialog
                                ? (bulkDialog.action.dialogTitle ??
                                  bulkDialog.action.label.replace(/\.{3}$/, ""))
                                : null}
                        </DialogTitle>
                        <DialogDescription>
                            {bulkDialog
                                ? `Applies to ${bulkDialog.ids.length} selected item(s).`
                                : null}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        {(bulkDialog?.action as ResourceBulkAction | undefined)?.fields?.map((field) => (
                            <ResourceField
                                key={field.name}
                                idPrefix="bulk-field"
                                context={{
                                    field,
                                    record: bulkDialog?.values ?? {},
                                    value: bulkDialog ? readPath(bulkDialog.values, field.name) : undefined,
                                    setValue: (value) => setBulkValue(field.name, value),
                                    setField: (path, value) => setBulkValue(path, value),
                                }}
                            />
                        ))}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={closeBulkDialog}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={submitBulkDialog} disabled={!bulkDialogReady}>
                            Apply
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={confirming !== null} onOpenChange={(open) => !open && setConfirming(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{confirming?.title}</AlertDialogTitle>
                        {confirming?.description ? <AlertDialogDescription>{confirming.description}</AlertDialogDescription> : null}
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                confirming?.run();
                                setConfirming(null);
                            }}
                        >
                            {confirming?.confirmLabel ?? "Delete"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
