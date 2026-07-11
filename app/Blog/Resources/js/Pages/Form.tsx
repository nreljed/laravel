import { useState } from "react";
import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { X, Upload, Image as ImageIcon } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import ContentBlockEditor from "../components/ContentBlockEditor";
import PublishSection from "@/Components/ui/publish-section";
import { FeaturedImageSelector } from "@/Components/shared/featured-image-selector";

interface Category {
  id: number;
  name: string;
}

interface Tag {
  id: number;
  name: string;
}

interface ContentBlock {
  id?: number;
  type: string;
  content: string | null;
  media_url: string | null;
  media_alt: string | null;
  settings: Record<string, unknown> | null;
  order: number;
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
  content_blocks: ContentBlock[];
  meta_title: string | null;
  meta_description: string | null;
  og_image: string | null;
}

interface PostsFormProps {
  post: Post | null;
  categories: Category[];
  tags: Tag[];
}

export default function PostsForm({ post, categories, tags }: PostsFormProps) {
  const isEditing = !!post;
  const [tagInput, setTagInput] = useState("");

  const form = useForm({
    title: post?.title || "",
    content: post?.content || "",
    excerpt: post?.excerpt || "",
    featured_image: post?.featured_image || "",
    status: post?.status || "draft",
    publish_date: post?.published_at || "",
    category_id: post?.category_id ? String(post.category_id) : "",
    tag_ids: post?.tags?.map((t) => t.id) || ([] as number[]),
    meta_title: post?.meta_title || "",
    meta_description: post?.meta_description || "",
    og_image: post?.og_image || "",
    content_blocks: post?.content_blocks || [],
  });

  const addTag = async () => {
    const tag = tagInput.trim();
    if (tag) {
      const existing = tags.find((t) => t.name.toLowerCase() === tag.toLowerCase());
      if (existing) {
        if (!form.data.tag_ids.includes(existing.id)) {
          form.setData("tag_ids", [...form.data.tag_ids, existing.id]);
        }
      } else {
        try {
          const res = await fetch(route("dashboard.posts.tags.store").toString(), {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "" },
            body: JSON.stringify({ name: tag }),
          });
          const data = await res.json();
          if (res.ok && data.id) {
            tags.push({ id: data.id, name: data.name, slug: data.slug, posts_count: 0 });
            form.setData("tag_ids", [...form.data.tag_ids, data.id]);
          }
        } catch { /* ignore */ }
      }
    }
    setTagInput("");
  };

  const removeTagId = (id: number) => {
    form.setData("tag_ids", form.data.tag_ids.filter((t) => t !== id));
  };

  const handleUpload = (field: "featured_image" | "og_image") => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.onchange = () => {
      const file = input.files?.[0];
      if (!file) return;
      const formData = new FormData();
      formData.append("file", file);
      router.post(route("dashboard.posts.upload-image"), formData, {
        onSuccess: (page) => {
          const res = page.props as Record<string, unknown>;
          const uploadRes = res.upload as { url: string } | undefined;
          if (uploadRes?.url) {
            form.setData(field, uploadRes.url);
            toast.success("Image uploaded.");
          }
        },
        onError: () => toast.error("Upload failed."),
      });
    };
    input.click();
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => { toast.success(isEditing ? "Post updated." : "Post created."); },
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
            <Tabs defaultValue="content">
              <TabsList>
                <TabsTrigger value="content">Content</TabsTrigger>
                <TabsTrigger value="blocks">Blocks</TabsTrigger>
                <TabsTrigger value="seo">SEO</TabsTrigger>
              </TabsList>

              <TabsContent value="content" className="space-y-6 mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>{isEditing ? "Edit Post" : "Add Post"}</CardTitle>
                    <CardDescription>Write your post content and set basic options.</CardDescription>
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
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="blocks">
                <Card>
                  <CardHeader>
                    <CardTitle>Content Blocks</CardTitle>
                    <CardDescription>Add structured content blocks (rich text, images, embeds, etc.).</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <ContentBlockEditor
                      blocks={form.data.content_blocks}
                      onChange={(blocks) => form.setData("content_blocks", blocks)}
                    />
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="seo" className="space-y-6 mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>SEO Settings</CardTitle>
                    <CardDescription>Optimize your post for search engines.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid gap-2">
                      <Label htmlFor="meta_title">Meta Title</Label>
                      <Input id="meta_title" value={form.data.meta_title} onChange={(e) => form.setData("meta_title", e.target.value)} maxLength={70} placeholder="SEO title (max 70 chars)" />
                      <p className="text-xs text-muted-foreground">{form.data.meta_title.length}/70</p>
                    </div>

                    <div className="grid gap-2">
                      <Label htmlFor="meta_description">Meta Description</Label>
                      <Textarea id="meta_description" value={form.data.meta_description} onChange={(e) => form.setData("meta_description", e.target.value)} maxLength={160} placeholder="SEO description (max 160 chars)" />
                      <p className="text-xs text-muted-foreground">{form.data.meta_description.length}/160</p>
                    </div>

                    <div className="grid gap-2">
                      <Label>OG Image</Label>
                      <div className="flex items-center gap-3">
                        <Input value={form.data.og_image} onChange={(e) => form.setData("og_image", e.target.value)} placeholder="Open Graph image URL..." className="flex-1" />
                        <Button type="button" variant="outline" onClick={() => handleUpload("og_image")}>
                          <Upload className="mr-1 h-4 w-4" /> Upload
                        </Button>
                      </div>
                      {form.data.og_image && (
                        <img src={form.data.og_image} alt="OG" className="mt-2 h-24 w-auto rounded-md border" />
                      )}
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>

          <div className="space-y-6">
            <PublishSection
              status={form.data.status}
              publishDate={form.data.publish_date}
              onStatusChange={(status) => form.setData("status", status)}
              onPublishDateChange={(date) => form.setData("publish_date", date)}
            />

            <FeaturedImageSelector
              imageUrl={form.data.featured_image}
              onUpload={(url) => form.setData("featured_image", url)}
              onRemove={() => form.setData("featured_image", "")}
            />

            <Card>
              <CardHeader>
                <CardTitle>Category & Tags</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-2">
                  <Label>Category</Label>
                  <Select
                    value={form.data.category_id}
                    onValueChange={(v) => form.setData("category_id", v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">None</SelectItem>
                      {categories.map((cat) => (
                        <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="grid gap-2">
                  <Label>Tags</Label>
                  <div className="flex gap-2">
                    <Select value="" onValueChange={(v) => { const val = Number(v); if (val && !form.data.tag_ids.includes(val)) { form.setData("tag_ids", [...form.data.tag_ids, val]); } }}>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="Select tag..." />
                      </SelectTrigger>
                      <SelectContent>
                        {tags.filter((t) => !form.data.tag_ids.includes(t.id)).map((tag) => (
                          <SelectItem key={tag.id} value={String(tag.id)}>{tag.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <Input
                      value={tagInput}
                      onChange={(e) => setTagInput(e.target.value)}
                      onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addTag(); } }}
                      placeholder="New tag..."
                      className="w-32"
                    />
                    <Button type="button" variant="outline" onClick={addTag}>Add</Button>
                  </div>
                  {form.data.tag_ids.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                      {form.data.tag_ids.map((id) => {
                        const tag = tags.find((t) => t.id === id);
                        if (!tag) return null;
                        return (
                          <span key={id} className="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium">
                            {tag.name}
                            <button type="button" onClick={() => removeTagId(id)} className="hover:text-destructive">
                              <X className="h-3 w-3" />
                            </button>
                          </span>
                        );
                      })}
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            <div className="flex justify-end gap-3">
              <Button type="submit" disabled={form.processing}>
                {form.processing ? "Saving..." : isEditing ? "Update Post" : "Create Post"}
              </Button>
            </div>
          </div>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
