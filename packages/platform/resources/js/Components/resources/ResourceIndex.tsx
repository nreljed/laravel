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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "../ui/dropdown-menu";
import { MoreHorizontal } from "lucide-react";
import { toColumnDef, type ResourceDefinition, type ResourceRecord, type ResourceRowAction } from "../../lib/resource-schema";

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
}

export function ResourceIndex<TData extends ResourceRecord = ResourceRecord>({
    resource,
    data,
    createRoute,
    editRoute,
    destroyRoute,
    onRowAction,
    onBulkAction,
}: ResourceIndexProps<TData>) {
    const table = resource.table;
    const [confirming, setConfirming] = React.useState<{ title: string; description?: string; confirmLabel: string; run: () => void } | null>(null);

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
                    } else if (method === "put" || method === "patch") {
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

    // Thrown after every hook has run, so a resource that lost its table
    // declaration cannot change the hook count between renders.
    if (!table) {
        throw new Error(`Resource "${resource.key}" has no table declaration; ResourceIndex cannot render it.`);
    }

    return (
        <>
            <div className="flex items-center justify-between gap-4 pb-4">
                <h1 className="text-2xl font-semibold tracking-tight">{resource.label}</h1>
                {createRoute ? (
                    <Button render={<Link href={createRoute} />}>New {resource.singular ?? "item"}</Button>
                ) : null}
            </div>

            <DataTable
                columns={allColumns}
                data={data}
                searchKey={table.searchKey}
                searchKeys={table.searchKeys}
                searchPlaceholder={table.searchPlaceholder}
                filters={table.filters?.map((filter) => ({ ...filter }))}
                bulkActions={table.bulkActions}
                onBulkAction={onBulkAction}
                defaultPageSize={table.defaultPageSize}
                pageSizeOptions={table.pageSizeOptions}
                emptyState={table.emptyState}
                loading={table.loading}
            />

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
