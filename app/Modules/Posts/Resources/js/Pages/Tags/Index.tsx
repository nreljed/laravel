import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { Plus, Pencil, Trash2, Tag } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent } from "@/Components/ui/card";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";

interface TagItem {
  id: number;
  name: string;
  slug: string;
  posts_count: number;
}

interface Props {
  tags: TagItem[];
}

export default function PostTagsIndex({ tags }: Props) {
  const [editing, setEditing] = useState<TagItem | null>(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "" });

  const resetForm = () => {
    setForm({ name: "" });
    setEditing(null);
  };

  const openEdit = (tag: TagItem) => {
    setEditing(tag);
    setForm({ name: tag.name });
    setOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editing) {
      router.put(route("dashboard.posts.tags.update", editing.id), form, {
        preserveScroll: true,
        onSuccess: () => { setOpen(false); resetForm(); },
      });
    } else {
      router.post(route("dashboard.posts.tags.store"), form, {
        preserveScroll: true,
        onSuccess: () => { setOpen(false); resetForm(); },
      });
    }
  };

  const handleDelete = (tag: TagItem) => {
    if (!confirm(`Delete tag "${tag.name}"?`)) return;
    router.delete(route("dashboard.posts.tags.destroy", tag.id), { preserveScroll: true });
  };

  return (
    <AuthenticatedLayout header="Post Tags">
      <Head title="Post Tags" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Tags</h2>
          <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) resetForm(); }}>
            <DialogTrigger>
              <Button onClick={() => resetForm()}>
                <Plus className="mr-1 h-4 w-4" /> Add Tag
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{editing ? "Edit Tag" : "Add Tag"}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="tag-name">Name</Label>
                  <Input id="tag-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                </div>
                <Button type="submit" className="w-full">{editing ? "Update" : "Create"}</Button>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {tags.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon"><Tag /></EmptyMedia>
              <EmptyTitle>No tags yet</EmptyTitle>
              <EmptyDescription>Create tags to organize your posts.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Slug</TableHead>
                    <TableHead>Posts</TableHead>
                    <TableHead className="w-24">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tags.map((tag) => (
                    <TableRow key={tag.id}>
                      <TableCell className="font-medium">{tag.name}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{tag.slug}</TableCell>
                      <TableCell><Badge variant="secondary">{tag.posts_count}</Badge></TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon-sm" onClick={() => openEdit(tag)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button variant="ghost" size="icon-sm" onClick={() => handleDelete(tag)}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
