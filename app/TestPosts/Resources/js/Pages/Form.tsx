import { Head, useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { FileText, Image } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import PublishSection from "@/Components/shared/publish-section";
import { GalleryUploader } from "@/Components/shared/gallery-uploader";
import { MapSectionInner } from "@/Components/shared/map";
import type { AddressData } from "@/lib/geocoding";
import type { ServerGalleryImage } from "@/types/gallery";

interface TestPost {
  id: number;
  title: string;
  content: string;
  excerpt: string | null;
  featured_image: string | null;
  gallery_images: ServerGalleryImage[] | null;
  status: string;
  published_at: string | null;
  latitude: number | null;
  longitude: number | null;
  address: AddressData | null;
  map_zoom: number | null;
}

interface TestPostsFormProps {
  testPost: TestPost | null;
}

export default function TestPostsForm({ testPost }: TestPostsFormProps) {
  const isEditing = !!testPost;

  const form = useForm({
    title: testPost?.title || "",
    content: testPost?.content || "",
    excerpt: testPost?.excerpt || "",
    gallery_images: testPost?.gallery_images || ([] as ServerGalleryImage[]),
    status: testPost?.status || "draft",
    latitude: testPost?.latitude ?? (null as number | null),
    longitude: testPost?.longitude ?? (null as number | null),
    address: testPost?.address ?? (null as AddressData | null),
    map_zoom: testPost?.map_zoom ?? (null as number | null),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(isEditing ? "Test updated." : "Test created.");
      },
      onError: () => toast.error("Failed to save test."),
    };

    if (isEditing) {
      form.put(route("dashboard.test-posts.update", testPost!.id), options);
    } else {
      form.post(route("dashboard.test-posts.store"), options);
    }
  };

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Test" : "Add Test"}>
      <Head title={isEditing ? "Edit Test" : "Add Test"} />

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
          <div className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <FileText className="h-5 w-5 text-foreground" />
                  {isEditing ? "Edit Test" : "Add Test"}
                </CardTitle>
                <CardDescription>Create test data for your application.</CardDescription>
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
                  <Textarea
                    id="content"
                    rows={6}
                    value={form.data.content}
                    onChange={(e) => form.setData("content", e.target.value)}
                    placeholder="Write your test content..."
                  />
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

            <MapSectionInner
              latitude={form.data.latitude}
              longitude={form.data.longitude}
              address={form.data.address}
              mapZoom={form.data.map_zoom}
              onLatitudeChange={(lat) => form.setData("latitude", lat)}
              onLongitudeChange={(lng) => form.setData("longitude", lng)}
              onAddressChange={(addr) => form.setData("address", addr)}
              onMapZoomChange={(zoom) => form.setData("map_zoom", zoom)}
              errors={form.errors}
            />

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle className="flex items-center gap-2 text-foreground">
                  <Image className="h-5 w-5 text-foreground" />
                  Gallery Images
                </CardTitle>
                <CardDescription>Upload and manage gallery images for this test.</CardDescription>
              </CardHeader>
              <CardContent>
                <GalleryUploader
                  images={form.data.gallery_images}
                  onChange={(images) => form.setData("gallery_images", images)}
                  uploadUrl={route("dashboard.test-posts.upload-image").toString()}
                  deleteUrl={route("dashboard.test-posts.delete-image").toString()}
                  maxImages={20}
                />
                {form.errors.gallery_images && (
                  <p className="text-sm text-destructive">{form.errors.gallery_images}</p>
                )}
              </CardContent>
            </Card>
          </div>

          <div className="space-y-6">
            <PublishSection
              status={form.data.status}
              onStatusChange={(status) => form.setData("status", status)}
              onSubmit={handleSubmit}
              loading={form.processing}
            />
          </div>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
