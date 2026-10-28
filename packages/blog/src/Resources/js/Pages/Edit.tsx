import * as React from "react";
import { Head, router, usePage, useForm } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";
import { toast } from "@nreljed/platform/Components/ui/toast";
import {
  FolderTree,
  Tag,
  Image as ImageIcon,
  CircleDot,
  XIcon,
  AlertTriangle,
} from "lucide-react";

import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { useMediaQuery } from "@nreljed/platform/hooks/use-media-query";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Field, FieldError } from "@nreljed/platform/Components/ui/field";
import { Spinner } from "@nreljed/platform/Components/ui/spinner";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { ScrollArea } from "@nreljed/platform/Components/ui/scroll-area";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@nreljed/platform/Components/ui/alert-dialog";
import { Sheet, SheetClose, SheetContent, SheetHeader, SheetTitle } from "@nreljed/platform/Components/ui/sheet";
import { PostStatusSection } from "@nreljed/platform/Components/shared/post-status-section";
import { FeaturedImageSelector } from "@nreljed/platform/Components/shared/featured-image-selector";
import { TaxonomySelector } from "@nreljed/platform/Components/shared/taxonomy-selector";
import { SidebarSection } from "@nreljed/platform/Components/shared/sidebar-section";
import { SeoPanel, type SeoData } from "../Components/seo/SeoPanel";
import { PostEditor } from "../components/PostEditor";
import { PostEditorToolbar, type ViewportSize } from "../components/PostEditorToolbar";

interface CategoryTreeNode {
  id: number;
  name: string;
  parent: number | null;
  count: number;
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
  meta_title: string;
  meta_description: string;
  og_image: string | null;
  focus_keyword: string | null;
  metadata: Record<string, unknown> | null;
  tags: Tag[];
}

interface PostEditFormData {
  title: string;
  content: string;
  excerpt: string;
  status: string;
  publish_date: string | null;
  category_id: number | null;
  tag_ids: number[];
  featured_image: string;
  meta_title: string;
  meta_description: string;
  og_image: string;
  focus_keyword: string;
  seo_metadata: SeoData | null;
}

interface PostEditPageProps extends PageProps {
  post?: Post;
  categories?: CategoryTreeNode[];
  tags?: Tag[];
  preview_url?: string | null;
}

const PostEdit = () => {
  const { props } = usePage<PostEditPageProps>();

  const post = props.post;
  const categories = (props.categories ?? []) as CategoryTreeNode[];
  const tags = (props.tags ?? []) as Tag[];
  const previewUrl = (props.preview_url as string) || "";

  const [sidebarOpen, setSidebarOpen] = React.useState(false);
  const [viewport, setViewport] = React.useState<ViewportSize>("desktop");
  const [deleteOpen, setDeleteOpen] = React.useState(false);
  const isLg = useMediaQuery("(min-width: 1024px)");
  const pushSidebar = sidebarOpen && isLg;

  const seoMetadata =
    ((post?.metadata as Record<string, unknown> | null)?.seo as SeoData | null) ?? null;

  const { data, setData, transform, put, processing, errors, setError, clearErrors } =
    useForm<PostEditFormData>({
      title: (post?.title as string) || "",
      content: (post?.content as string) || "",
      excerpt: (post?.excerpt as string) || "",
      status: (post?.status as string) || "published",
      publish_date: (post?.published_at as string | null) || null,
      category_id: post?.category_id ? Number(post.category_id) : null,
      tag_ids: (post?.tags?.map((t) => t.id) as number[]) || [],
      featured_image: (post?.featured_image as string) || "",
      meta_title: (post?.meta_title as string) || "",
      meta_description: (post?.meta_description as string) || "",
      og_image: (post?.og_image as string) || "",
      focus_keyword: (post?.focus_keyword as string) || "",
      seo_metadata: seoMetadata,
    });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const contentValid = data.content && data.content.trim() !== "" && data.content !== "<p></p>";
    if (!data.title.trim() || !contentValid) {
      if (!data.title.trim()) setError("title", "Title is required");
      if (!contentValid) setError("content", "Content is required");
      return;
    }

    transform((formData: PostEditFormData) => ({
      ...formData,
      publish_date: data.status === "future" ? data.publish_date : null,
    }));
    put(route("dashboard.posts.update", post?.id as number), {
      onError: (error: Record<string, string>) => {
        if (import.meta.env.DEV) console.error("Update failed:", error);
        toast.add({ title: "An error occurred while saving. Please try again.", type: "error" });
      },
    });
  };

  const handleDeletePost = () => {
    setDeleteOpen(false);
    router.delete(route("dashboard.posts.destroy", post?.id as number), {
      preserveScroll: true,
      onError: () => {
        toast.add({ title: "Failed to delete the post. Please try again.", type: "error" });
      },
    });
  };

  const handleSeoChange = (field: string, value: string) => {
    if (field === "keyword") {
      setData("focus_keyword", value);
    } else if (field === "meta_title" || field === "meta_description") {
      setData(field as "meta_title" | "meta_description", value);
    }
  };

  const handleSeoGenerated = (seo: SeoData) => {
    if (seo.suggested_category_id && !data.category_id) {
      setData("category_id", seo.suggested_category_id);
    }

    const matchedTagIds = (seo.suggested_tag_ids ?? []).filter((id) => id > 0);
    if (matchedTagIds.length > 0) {
      const merged = Array.from(new Set([...data.tag_ids, ...matchedTagIds]));
      if (merged.length !== data.tag_ids.length) {
        setData("tag_ids", merged);
      }
    }
  };

  return (
    <AuthenticatedLayout header="Edit Post" maxWidth="max-w-full">
      <Head title="Edit Post" />
      <div className={`transition-all duration-300 ease-in-out ${pushSidebar ? "lg:mr-96" : ""}`}>
        <PostEditorToolbar
          title={String(data.title) || "Edit Post"}
          subtitle="Edit your blog post"
          backUrl={route("dashboard.posts.index")}
          sidebarOpen={sidebarOpen}
          onToggleSidebar={() => setSidebarOpen((open) => !open)}
          onSave={() => handleSubmit({ preventDefault: () => {} } as React.FormEvent)}
          saving={processing}
          saveLabel="Update"
          viewport={viewport}
          onViewportChange={setViewport}
          previewUrl={previewUrl}
        />

        {/* Full-width content area */}
        <div className="mx-auto py-6 transition-all duration-300">
          <form onSubmit={handleSubmit} className="space-y-6">
            <Field>
              <Label htmlFor="title">
                Title <span className="text-destructive">*</span>
              </Label>
              <Input
                id="title"
                type="text"
                placeholder="Enter post title"
                value={data.title}
                onChange={(e) => {
                  setData("title", e.target.value);
                  if (errors.title) clearErrors("title");
                }}
                aria-invalid={!!errors.title}
              />
              {errors.title && (
                <FieldError>
                  <p className="text-sm">{errors.title}</p>
                </FieldError>
              )}
            </Field>

            <Field>
              <Label htmlFor="content">
                Content <span className="text-destructive">*</span>
              </Label>
              <PostEditor
                value={data.content}
                onChange={(val: string) => {
                  setData("content", val);
                  if (errors.content) clearErrors("content");
                }}
                viewport={viewport}
              />
              {errors.content && (
                <FieldError>
                  <p className="text-sm">{errors.content}</p>
                </FieldError>
              )}
            </Field>

            <Field>
              <Label htmlFor="excerpt">Excerpt</Label>
              <Input
                id="excerpt"
                type="text"
                placeholder="Short description (optional)"
                value={data.excerpt}
                onChange={(e) => setData("excerpt", e.target.value)}
              />
            </Field>

            <SeoPanel
              title={data.title}
              content={data.content}
              metaTitle={data.meta_title}
              metaDescription={data.meta_description}
              ogImage={data.og_image}
              keyword={data.focus_keyword}
              savedSeoData={data.seo_metadata ?? null}
              onChange={handleSeoChange}
              onSeoGenerated={handleSeoGenerated}
            />
          </form>
        </div>
      </div>

      {/* Settings sidebar */}
      <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen} modal={isLg ? false : "trap-focus"}>
        <SheetContent
          side="right"
          className="data-[side=right]:w-[90vw] sm:max-w-sm p-0 gap-0"
          showCloseButton={false}
          noOverlay={isLg}
        >
          <SheetHeader className="flex flex-row items-center justify-between border-b border-border px-4 py-3">
            <SheetTitle className="text-foreground">Post Settings</SheetTitle>
            <SheetClose
              render={<Button variant="ghost" size="icon-sm" aria-label="Close settings" />}
            >
              <XIcon className="h-4 w-4" />
            </SheetClose>
          </SheetHeader>

          <ScrollArea className="flex-1 min-h-0">
            <div className="space-y-0">
              {/* Featured Image */}
              <SidebarSection icon={ImageIcon} title="Featured Image">
                <FeaturedImageSelector
                  value={data.featured_image}
                  onChange={(url: string) => setData("featured_image", url)}
                  uploadUrl={route("dashboard.posts.upload-image")}
                  deleteUrl={route("dashboard.posts.delete-image")}
                  label=""
                  description=""
                />
              </SidebarSection>

              <Separator />

              {/* Publish */}
              <SidebarSection icon={CircleDot} title="Status">
                <PostStatusSection
                  status={data.status}
                  publishDate={data.publish_date}
                  onStatusChange={(value: string) => setData("status", value)}
                  onDateChange={(value: string | null) => setData("publish_date", value)}
                  disabled={processing}
                  showDelete={!!post?.id}
                  deleteDisabled={processing}
                  onDelete={() => setDeleteOpen(true)}
                />
              </SidebarSection>

              <Separator />

              {/* Categories */}
              <SidebarSection
                icon={FolderTree}
                title="Categories"
                badge={data.category_id ? 1 : null}
              >
                {categories.length > 0 ? (
                  <TaxonomySelector
                    taxonomies={categories}
                    selectedIds={data.category_id ? [data.category_id] : []}
                    onSelectionChange={(ids: (string | number)[]) =>
                      setData("category_id", ids.length ? Number(ids[ids.length - 1]) : null)
                    }
                    config={{
                      multiSelect: false,
                      ariaLabel: "Select category",
                      showCounts: true,
                      showExpandCollapseAll: false,
                      maxHeight: 300,
                    }}
                  />
                ) : (
                  <p className="text-sm text-muted-foreground">No categories available</p>
                )}
              </SidebarSection>

              <Separator />

              {/* Tags */}
              <SidebarSection
                icon={Tag}
                title="Tags"
                badge={data.tag_ids.length > 0 ? data.tag_ids.length : null}
              >
                {tags.length > 0 ? (
                  <TaxonomySelector
                    taxonomies={tags}
                    selectedIds={data.tag_ids}
                    onSelectionChange={(ids: (string | number)[]) =>
                      setData("tag_ids", ids.map(Number))
                    }
                    config={{
                      multiSelect: true,
                      ariaLabel: "Select tags",
                      showCounts: true,
                      showExpandCollapseAll: false,
                      indeterminateStates: false,
                      maxHeight: 300,
                    }}
                  />
                ) : (
                  <p className="text-sm text-muted-foreground">No tags available</p>
                )}
              </SidebarSection>
            </div>
          </ScrollArea>

          {/* Footer with Update */}
          <div className="border-t border-border px-4 py-3">
            <Button
              type="button"
              onClick={handleSubmit}
              disabled={processing || !post?.id}
              className="w-full"
            >
              {processing && <Spinner className="h-4 w-4 mr-2" />}
              Update
            </Button>
          </div>
        </SheetContent>
      </Sheet>

      {/* Delete confirmation dialog */}
      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              Delete Post
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete &ldquo;{data.title || "Untitled"}&rdquo;? This action
              cannot be undone and the post will be permanently removed.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={handleDeletePost}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AuthenticatedLayout>
  );
};

export default PostEdit;