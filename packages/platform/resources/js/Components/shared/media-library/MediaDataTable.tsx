/**
 * MediaDataTable - list view for the Media Library as a real DataTable with row
 * actions (mirrors the Posts list pattern instead of the plain grid-style list).
 */
import * as React from "react";
import { toast } from "../../ui/toast";
import { DataTable } from "../../ui/data-table";
import { DataTableColumnHeader } from "../../ui/data-table-column-header";
import { Button } from "../../ui/button";
import { Skeleton } from "../../ui/skeleton";
import {
  Image as ImageIcon,
  MoreHorizontal,
  Trash2,
  ExternalLink,
  Link2,
  AlertTriangle,
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "../../ui/dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "../../ui/alert-dialog";
import type { ColumnDef } from "@tanstack/react-table";
import type { AppTableFeatures } from "../../../lib/table-features";
import { formatFileSize } from "../../../lib/gallery-utils";
import type { MediaItem } from "./types";

interface MediaDataTableProps {
  items: MediaItem[];
  loading?: boolean;
  onDelete: (_item: MediaItem) => Promise<boolean>;
  onBulkDelete: (_items: MediaItem[]) => Promise<void>;
  /** Built-in toolbar search. Pass `undefined` when an external (server-side) search exists. */
  searchKey?: string;
}

function MediaRowActions({
  item,
  onDelete,
}: {
  item: MediaItem;
  onDelete: (_item: MediaItem) => Promise<boolean>;
}) {
  const [deleteOpen, setDeleteOpen] = React.useState(false);
  const [deleting, setDeleting] = React.useState(false);

  const copyUrl = async (): Promise<void> => {
    try {
      await navigator.clipboard.writeText(item.url);
      toast.add({ title: "URL copied", type: "success" });
    } catch {
      toast.add({ title: "Failed to copy URL", type: "error" });
    }
  };

  const handleDelete = async (): Promise<void> => {
    setDeleting(true);
    await onDelete(item);
    setDeleting(false);
    setDeleteOpen(false);
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
          <MoreHorizontal className="h-4 w-4" />
          <span className="sr-only">Open menu</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={copyUrl}>
            <Link2 className="mr-2 h-4 w-4" />
            Copy URL
          </DropdownMenuItem>
          <DropdownMenuItem>
            <a
              href={item.url}
              target="_blank"
              rel="noreferrer"
              className="flex items-center w-full"
            >
              <ExternalLink className="mr-2 h-4 w-4" />
              View
            </a>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={() => setDeleteOpen(true)}
            className="text-destructive focus:text-destructive"
          >
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={deleteOpen} onOpenChange={(o) => !o && setDeleteOpen(false)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              Delete media
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{item.title || item.filename}"? This removes the file
              from your Media Library and cannot be undone. Posts referencing it may break.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={handleDelete}
              disabled={deleting}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              {deleting ? "Deleting..." : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

const getColumns = (
  onDelete: (_item: MediaItem) => Promise<boolean>,
): ColumnDef<AppTableFeatures, MediaItem>[] => [
  {
    accessorKey: "thumbnail",
    header: "Image",
    cell: ({ row }) => {
      const item = row.original;
      return (
        <div>
          {item.url ? (
            <img
              src={item.url}
              alt={item.title || item.filename}
              loading="lazy"
              className="!w-[60px] !h-[60px] object-cover rounded-md max-w-[60px]"
            />
          ) : (
            <div className="!w-[60px] !h-[60px] bg-muted rounded flex items-center justify-center">
              <ImageIcon className="h-6 w-6 text-muted-foreground" />
            </div>
          )}
        </div>
      );
    },
    enableSorting: false,
  },
  {
    accessorKey: "title",
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => {
      const item = row.original;
      return (
        <div className="min-w-0">
          <p
            className="font-medium text-foreground whitespace-normal truncate"
            title={item.title || item.filename}
          >
            {item.title || item.filename}
          </p>
          {item.filename && item.filename !== item.title && (
            <p className="text-xs text-muted-foreground truncate">{item.filename}</p>
          )}
        </div>
      );
    },
  },
  {
    accessorKey: "folder",
    header: "Folder",
    cell: ({ row }) => {
      const item = row.original;
      return <span className="text-sm text-muted-foreground truncate block">{item.folder}</span>;
    },
    enableSorting: false,
  },
  {
    accessorKey: "date",
    header: ({ column }) => <DataTableColumnHeader column={column} title="Uploaded" />,
    cell: ({ row }) => {
      const item = row.original;
      const label = item.date ? new Date(item.date).toLocaleDateString() : "\u{2014}";
      return <span className="text-sm text-foreground whitespace-nowrap">{label}</span>;
    },
    enableSorting: false,
  },
  {
    accessorKey: "size",
    header: "Size",
    cell: ({ row }) => (
      <span className="text-sm text-muted-foreground whitespace-nowrap">
        {formatFileSize(row.original.size)}
      </span>
    ),
    enableSorting: false,
  },
  {
    id: "actions",
    header: "Actions",
    cell: ({ row }) => <MediaRowActions item={row.original} onDelete={onDelete} />,
    enableSorting: false,
  },
];

export function MediaDataTable({
  items,
  loading = false,
  onDelete,
  onBulkDelete,
  searchKey = "title",
}: MediaDataTableProps) {
  const columns = React.useMemo(() => getColumns(onDelete), [onDelete]);

  const handleBulkAction = async (action: string, ids: (number | string)[]): Promise<void> => {
    if (action !== "delete") return;
    const targets = items.filter((i) => ids.includes(i.id));
    await onBulkDelete(targets);
  };

  if (loading && items.length === 0) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 6 }, (_, i) => (
          <div key={i} className="flex items-center gap-3 rounded-md border p-3">
            <Skeleton className="h-12 w-12 rounded-md" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-3 w-1/3" />
              <Skeleton className="h-3 w-1/4" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <DataTable
      columns={columns}
      data={items}
      searchKey={searchKey}
      searchPlaceholder="Search media..."
      bulkActions={[{ label: "Delete", value: "delete", destructive: true }]}
      onBulkAction={handleBulkAction}
    />
  );
}