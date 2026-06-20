import { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { X } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";

interface Post {
  id: number;
  title: string;
  content: string;
  excerpt: string | null;
  status: string;
  category: string | null;
  tags: string[] | null;
}

interface PostsFormProps {
  post: Post | null;
}

export default function PostsForm({ post }: PostsFormProps) {
  const isEditing = !!post;
  const [tagInput, setTagInput] = useState("");

  const form = useForm({
    title: post?.title || "",
    content: post?.content || "",
    excerpt: post?.excerpt || "",
    status: post?.status || "draft",
    category: post?.category || "",
    tags: post?.tags || ([] as string[]),
  });

  const addTag = () => {
    const tag = tagInput.trim();
    if (tag && !form.data.tags.includes(tag)) {
      form.setData("tags", [...form.data.tags, tag]);
    }
    setTagInput("");
  };

  const removeTag = (tag: string) => {
    form.setData("tags", form.data.tags.filter((t) => t !== tag));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => toast.success(isEditing ? "Post updated." : "Post created."),
      onError: () => toast.error("Failed to save post."),
    };

    if (isEditing) {
      form.put(route("dashboard.posts.update", post!.id), options);
    } else {
      form.post(route("dashboard.posts.store"), options);
    }
  };

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Post" : "Add Post"}>
      <Head title={isEditing ? "Edit Post" : "Add Post"} />

      <div className="max-w-3xl">
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>{isEditing ? "Edit Post" : "Add Post"}</CardTitle>
              <CardDescription>
                {isEditing ? "Update your post." : "Write a new post."}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="title">Title</Label>
                <Input id="title" value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} />
                {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
              </div>

              <div className="grid gap-2">
                <Label htmlFor="content">Content</Label>
                <Textarea id="content" rows={12} value={form.data.content} onChange={(e) => form.setData("content", e.target.value)} />
                {form.errors.content && <p className="text-sm text-destructive">{form.errors.content}</p>}
              </div>

              <div className="grid gap-2">
                <Label htmlFor="excerpt">Excerpt</Label>
                <Textarea id="excerpt" rows={2} value={form.data.excerpt} onChange={(e) => form.setData("excerpt", e.target.value)} />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="status">Status</Label>
                  <select
                    id="status"
                    value={form.data.status}
                    onChange={(e) => form.setData("status", e.target.value)}
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                  >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="category">Category</Label>
                  <Input id="category" value={form.data.category} onChange={(e) => form.setData("category", e.target.value)} />
                </div>
              </div>

              <div className="grid gap-2">
                <Label>Tags</Label>
                <div className="flex gap-2">
                  <Input
                    value={tagInput}
                    onChange={(e) => setTagInput(e.target.value)}
                    onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addTag(); } }}
                    placeholder="Type a tag and press Enter"
                  />
                  <Button type="button" variant="outline" onClick={addTag}>Add</Button>
                </div>
                {form.data.tags.length > 0 && (
                  <div className="flex flex-wrap gap-1 mt-1">
                    {form.data.tags.map((tag) => (
                      <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium">
                        {tag}
                        <button type="button" onClick={() => removeTag(tag)} className="hover:text-destructive">
                          <X className="h-3 w-3" />
                        </button>
                      </span>
                    ))}
                  </div>
                )}
              </div>

              <Button type="submit" disabled={form.processing}>
                {form.processing ? "Saving..." : isEditing ? "Update Post" : "Create Post"}
              </Button>
            </CardContent>
          </Card>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
