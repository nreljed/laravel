import { Head, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { FileText, Image, FolderOpen, Tag } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { RichTextEditor } from "@/Components/editor";
import { Label } from "@/Components/ui/label";
import { TermsSelector } from "@/Components/shared/terms-selector";
import PublishSection from "@/Components/shared/publish-section";
import { FeaturedImageSelector } from "@/Components/shared/featured-image-selector";

interface Category {
  id: number;
  name: string;
}

interface Tag {
  id: number;
  name: string;
}

interface Post {
  id: number;
  title: string;
  content: string;
  excerpt: string | null;
  featured_image: string | null;
  status: string;
  published_at: string | null;
  category_id: number | null;
  tags: Tag[];
}

interface PostsFormProps {
  post: Post | null;
  categories: Category[];
  tags: Tag[];
}

export default function PostsForm({ post, categories, tags }: PostsFormProps) {
  const isEditing = !!post;

  const form = useForm({
    title: post?.title || "",
    content: post?.content || "",
    excerpt: post?.excerpt || "",
    featured_image: post?.featured_image || "",
    status: post?.status || "published",
    publish_date: post?.published_at || "",
    category_id: post?.category_id ? Number(post.category_id) : (null as number | null),
    tag_ids: post?.tags?.map((t) => t.id) || ([] as number[]),
  });

  const handleAddTag = async (name: string) => {
    const res = await fetch(route("dashboard.posts.tags.store").toString(), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN":
          (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
      },
      body: JSON.stringify({ name }),
    });
    const data = await res.json();
    if (res.ok && data.id) {
      tags.push({ id: data.id, name: data.name });
      return { id: data.id };
    }
    throw new Error(data.message || "Failed to create tag");
  };

  const handleAddCategory = async (name: string) => {
    const res = await fetch(route("dashboard.posts.categories.store").toString(), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN":
          (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
      },
      body: JSON.stringify({ name }),
    });
    const data = await res.json();
    if (res.ok && data.id) {
      categories.push({ id: data.id, name: data.name });
      return { id: data.id };
    }
    throw new Error(data.message || "Failed to create category");
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(isEditing ? "Post updated." : "Post created.");
      },
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

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
          <div className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <FileText className="h-5 w-5 text-foreground" />
                  {isEditing ? "Edit Post" : "Add Post"}
                </CardTitle>
                <CardDescription>Write your post content and set basic options.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="title">Title</Label>
                  <Input
                    id="title"
                    value={form.data.title}
                    onChange={(e) => form.setData("title", e.target.value)}
                  />
                  {form.errors.title && (
                    <p className="text-sm text-destructive">{form.errors.title}</p>
                  )}
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="content">Content</Label>
                  <RichTextEditor
                    value={form.data.content}
                    onChange={(html) => form.setData("content", html)}
                    placeholder="Write your post content..."
                    maxLength={5000}
                  />
                  {form.errors.content && (
                    <p className="text-sm text-destructive">{form.errors.content}</p>
                  )}
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="excerpt">Excerpt</Label>
                  <Textarea
                    id="excerpt"
                    rows={2}
                    value={form.data.excerpt}
                    onChange={(e) => form.setData("excerpt", e.target.value)}
                  />
                </div>
              </CardContent>
            </Card>
          </div>

          <div className="space-y-6">
            <PublishSection
              status={form.data.status}
              publishDate={form.data.publish_date}
              onStatusChange={(status) => form.setData("status", status)}
              onDateChange={(date) => form.setData("publish_date", date)}
              onSubmit={handleSubmit}
              loading={form.processing}
            />

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <Image className="h-5 w-5 text-foreground" />
                  Featured Image
                </CardTitle>
                <CardDescription>Upload or select a featured image for this post.</CardDescription>
              </CardHeader>
              <CardContent>
                <FeaturedImageSelector
                  value={form.data.featured_image}
                  onChange={(url) => form.setData("featured_image", url)}
                  label=""
                  description=""
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <FolderOpen className="h-5 w-5 text-foreground" />
                  Category
                </CardTitle>
                <CardDescription>Assign a category to organize your post.</CardDescription>
              </CardHeader>
              <CardContent>
                <TermsSelector
                  options={categories}
                  value={form.data.category_id ? [form.data.category_id] : []}
                  onChange={(v) =>
                    form.setData("category_id", v.length > 0 ? Number(v.at(-1)) : null)
                  }
                  onAddTerm={handleAddCategory}
                  taxonomyName="Category"
                  placeholder="Select category..."
                  canAdd
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <Tag className="h-5 w-5 text-foreground" />
                  Tags
                </CardTitle>
                <CardDescription>Add tags to improve discoverability.</CardDescription>
              </CardHeader>
              <CardContent>
                <TermsSelector
                  options={tags}
                  value={form.data.tag_ids}
                  onChange={(v) => form.setData("tag_ids", v.map(Number))}
                  onAddTerm={handleAddTag}
                  taxonomyName="Tag"
                  placeholder="Select tags..."
                  canAdd
                />
              </CardContent>
            </Card>
          </div>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
