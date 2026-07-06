import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Button } from "@/Components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { MoreHorizontal, Pencil, Trash2, Eye, FileEdit, AlertTriangle } from "lucide-react";

export interface ActionItem {
  id: number | string;
  title?: string;
  status?: string;
  [key: string]: unknown;
}

interface ActionCellProps {
  item: ActionItem;
  baseRoute: string;
  itemLabel: string;
  nonce?: string;
  extraParams?: Record<string, string>;
}

export function ActionCell({
  item,
  baseRoute,
  itemLabel,
  nonce: _nonce,
  extraParams,
}: ActionCellProps) {
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [draftOpen, setDraftOpen] = useState(false);
  const [publishOpen, setPublishOpen] = useState(false);

  const handleDelete = () => {
    router.delete(`${baseRoute}/${item.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success(`${itemLabel} deleted successfully.`),
      onError: () => toast.error(`Failed to delete ${itemLabel.toLowerCase()}.`),
    });
  };

  const handleStatusChange = (newStatus: string) => {
    router.put(
      `${baseRoute}/${item.id}/status`,
      {
        status: newStatus,
        ...extraParams,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          if (newStatus === "publish") toast.success(`${itemLabel} published!`);
          else toast.success(`${itemLabel} moved to draft.`);
        },
        onError: () => toast.error(`Failed to update ${itemLabel.toLowerCase()} status.`),
      },
    );
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" className="h-8 w-8 p-0">
            <MoreHorizontal className="h-4 w-4" />
            <span className="sr-only">Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" onCloseAutoFocus={(e: Event) => e.preventDefault()}>
          <DropdownMenuItem asChild>
            <Link href={`${baseRoute}/${item.id}/edit`} className="flex items-center w-full">
              <Pencil className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </DropdownMenuItem>
          {(item.status === "draft" || item.status === "future") && (
            <DropdownMenuItem onClick={() => setPublishOpen(true)}>
              <Eye className="mr-2 h-4 w-4" />
              Publish
            </DropdownMenuItem>
          )}
          {["publish", "published", "future"].includes(item.status ?? "") && (
            <DropdownMenuItem onClick={() => setDraftOpen(true)}>
              <FileEdit className="mr-2 h-4 w-4" />
              Make Draft
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setDeleteOpen(true)}>
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              Delete {itemLabel}
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{item.title}"? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={handleDelete}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={draftOpen} onOpenChange={setDraftOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <FileEdit className="h-5 w-5" />
              Change to Draft
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to change "{item.title}" to draft?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => handleStatusChange("draft")}>
              Yes, Change to Draft
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={publishOpen} onOpenChange={setPublishOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <Eye className="h-5 w-5" />
              Publish {itemLabel}
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to publish "{item.title}"?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => handleStatusChange("publish")}>
              Yes, Publish
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

export default ActionCell;
