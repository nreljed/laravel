import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
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
}

interface ActionCellProps {
  item: ActionItem;
  baseRoute: string;
  itemLabel: string;
}

export function ActionCell({ item, baseRoute, itemLabel }: ActionCellProps) {
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [draftOpen, setDraftOpen] = useState(false);
  const [publishOpen, setPublishOpen] = useState(false);

  const handleDelete = () => {
    router.delete(`${baseRoute}/${item.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.add({ title: `${itemLabel} deleted successfully.`, type: "success" }),
      onError: () =>
        toast.add({ title: `Failed to delete ${itemLabel.toLowerCase()}.`, type: "error" }),
    });
  };

  const handleStatusChange = (newStatus: string) => {
    router.put(
      `${baseRoute}/${item.id}/status`,
      { status: newStatus },
      {
        preserveScroll: true,
        onSuccess: () => {
          if (newStatus === "published") {
            toast.add({ title: `${itemLabel} published!`, type: "success" });
          } else {
            toast.add({ title: `${itemLabel} moved to draft.`, type: "success" });
          }
        },
        onError: () =>
          toast.add({
            title: `Failed to update ${itemLabel.toLowerCase()} status.`,
            type: "error",
          }),
      },
    );
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="ghost" size="icon-xs" />}>
          <MoreHorizontal className="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem
            render={
              <Link href={`${baseRoute}/${item.id}/edit`} className="flex items-center w-full" />
            }
          >
            <Pencil className="mr-2 size-4" />
            Edit
          </DropdownMenuItem>
          {(["draft", "archived", "future"] as string[]).includes(item.status ?? "") && (
            <DropdownMenuItem onClick={() => setPublishOpen(true)}>
              <Eye className="mr-2 size-4" />
              Publish
            </DropdownMenuItem>
          )}
          {item.status === "published" && (
            <DropdownMenuItem onClick={() => setDraftOpen(true)}>
              <FileEdit className="mr-2 size-4" />
              Make Draft
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setDeleteOpen(true)}>
            <Trash2 className="mr-2 size-4" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="size-5 text-destructive" />
              Delete {itemLabel}
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete &ldquo;{item.title}&rdquo;? This action cannot be
              undone.
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
              <FileEdit className="size-5" />
              Change to Draft
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to change &ldquo;{item.title}&rdquo; to draft?
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
              <Eye className="size-5" />
              Publish {itemLabel}
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to publish &ldquo;{item.title}&rdquo;?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => handleStatusChange("published")}>
              Yes, Publish
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

export default ActionCell;
