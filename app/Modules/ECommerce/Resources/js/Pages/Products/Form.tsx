import { Head, useForm, router } from "@inertiajs/react";
import { toast } from "sonner";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

interface Category {
  id: number;
  name: string;
}

interface Product {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  short_description: string | null;
  price: number;
  compare_price: number | null;
  cost_price: number | null;
  sku: string | null;
  barcode: string | null;
  stock_quantity: number;
  low_stock_threshold: number;
  track_stock: boolean;
  allow_backorders: boolean;
  status: string;
  type: string;
  is_featured: boolean;
  is_taxable: boolean;
  tax_class: string | null;
  weight: number | null;
  length: number | null;
  width: number | null;
  height: number | null;
  thumbnail_url: string | null;
  images: { url: string; path: string }[] | null;
  categories: Category[];
}

interface ProductsFormProps {
  product: Product | null;
  categories: Category[];
}

export default function ProductsForm({ product, categories }: ProductsFormProps) {
  const isEditing = !!product;
  const [uploading, setUploading] = useState(false);

  const form = useForm({
    name: product?.name || "",
    description: product?.description || "",
    short_description: product?.short_description || "",
    price: product?.price?.toString() || "",
    compare_price: product?.compare_price?.toString() || "",
    cost_price: product?.cost_price?.toString() || "",
    sku: product?.sku || "",
    barcode: product?.barcode || "",
    stock_quantity: product?.stock_quantity?.toString() || "0",
    low_stock_threshold: product?.low_stock_threshold?.toString() || "5",
    track_stock: product?.track_stock ?? true,
    allow_backorders: product?.allow_backorders ?? false,
    status: product?.status || "draft",
    type: product?.type || "simple",
    is_featured: product?.is_featured ?? false,
    is_taxable: product?.is_taxable ?? true,
    tax_class: product?.tax_class || "",
    weight: product?.weight?.toString() || "",
    length: product?.length?.toString() || "",
    width: product?.width?.toString() || "",
    height: product?.height?.toString() || "",
    categories: product?.categories?.map((c) => c.id) || [],
    thumbnail_url: product?.thumbnail_url || "",
    images: product?.images || [],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => toast.success(isEditing ? "Product updated." : "Product created."),
      onError: () => toast.error("Failed to save product."),
    };

    if (isEditing) {
      form.put(route("dashboard.ecommerce.products.update", product!.id), options);
    } else {
      form.post(route("dashboard.ecommerce.products.store"), options);
    }
  };

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const res = await router.post(
        route("dashboard.ecommerce.products.upload-image"),
        { file },
        { preserveState: true, preserveScroll: true },
      );
      const response = res as unknown as { url: string; path: string };
      if (response.url) {
        const images = [...(form.data.images || []), { url: response.url, path: response.path }];
        form.setData("images", images);
        if (!form.data.thumbnail_url) {
          form.setData("thumbnail_url", response.url);
        }
        toast.success("Image uploaded.");
      }
    } catch {
      toast.error("Upload failed.");
    } finally {
      setUploading(false);
    }
  };

  const removeImage = async (path: string) => {
    try {
      await router.delete(route("dashboard.ecommerce.products.delete-image"), {
        data: { path },
        preserveState: true,
        preserveScroll: true,
      });
      const images = (form.data.images || []).filter((img: { url: string; path: string }) => img.path !== path);
      form.setData("images", images);
      if (form.data.thumbnail_url?.includes(path)) {
        form.setData("thumbnail_url", images[0]?.url || "");
      }
      toast.success("Image removed.");
    } catch {
      toast.error("Failed to remove image.");
    }
  };

  const setThumbnail = (url: string) => {
    form.setData("thumbnail_url", url);
  };

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Product" : "Add Product"}>
      <Head title={isEditing ? "Edit Product" : "Add Product"} />

      <div className="max-w-3xl">
        <form onSubmit={handleSubmit}>
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>{isEditing ? "Edit Product" : "Add Product"}</CardTitle>
                <CardDescription>
                  {isEditing ? "Update product details." : "Create a new product."}
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="name">Name *</Label>
                  <Input id="name" value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} />
                  {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input id="sku" value={form.data.sku} onChange={(e) => form.setData("sku", e.target.value)} />
                    {form.errors.sku && <p className="text-sm text-destructive">{form.errors.sku}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="barcode">Barcode</Label>
                    <Input id="barcode" value={form.data.barcode} onChange={(e) => form.setData("barcode", e.target.value)} />
                  </div>
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="short_description">Short Description</Label>
                  <Textarea id="short_description" rows={2} value={form.data.short_description} onChange={(e) => form.setData("short_description", e.target.value)} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="description">Full Description</Label>
                  <Textarea id="description" rows={5} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Pricing</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-3 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="price">Price *</Label>
                    <Input id="price" type="number" step="0.01" min="0" value={form.data.price} onChange={(e) => form.setData("price", e.target.value)} />
                    {form.errors.price && <p className="text-sm text-destructive">{form.errors.price}</p>}
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="compare_price">Compare Price</Label>
                    <Input id="compare_price" type="number" step="0.01" min="0" value={form.data.compare_price} onChange={(e) => form.setData("compare_price", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="cost_price">Cost Price</Label>
                    <Input id="cost_price" type="number" step="0.01" min="0" value={form.data.cost_price} onChange={(e) => form.setData("cost_price", e.target.value)} />
                  </div>
                </div>

                <div className="flex items-center gap-6">
                  <label className="flex items-center gap-2">
                    <Switch
                      checked={form.data.is_taxable}
                      onCheckedChange={(v) => form.setData("is_taxable", v)}
                    />
                    <span className="text-sm">Taxable</span>
                  </label>
                  {form.data.is_taxable && (
                    <div className="grid gap-2">
                      <Label htmlFor="tax_class">Tax Class</Label>
                      <Input id="tax_class" value={form.data.tax_class} onChange={(e) => form.setData("tax_class", e.target.value)} className="w-40" />
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Inventory</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <label className="flex items-center gap-2 mb-2">
                  <Switch
                    checked={form.data.track_stock}
                    onCheckedChange={(v) => form.setData("track_stock", v)}
                  />
                  <span className="text-sm">Track Stock</span>
                </label>

                {form.data.track_stock && (
                  <>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="grid gap-2">
                        <Label htmlFor="stock_quantity">Stock Quantity</Label>
                        <Input id="stock_quantity" type="number" min="0" value={form.data.stock_quantity} onChange={(e) => form.setData("stock_quantity", e.target.value)} />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="low_stock_threshold">Low Stock Threshold</Label>
                        <Input id="low_stock_threshold" type="number" min="0" value={form.data.low_stock_threshold} onChange={(e) => form.setData("low_stock_threshold", e.target.value)} />
                      </div>
                    </div>
                    <label className="flex items-center gap-2">
                      <Switch
                        checked={form.data.allow_backorders}
                        onCheckedChange={(v) => form.setData("allow_backorders", v)}
                      />
                      <span className="text-sm">Allow Backorders</span>
                    </label>
                  </>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Organization</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select value={form.data.status} onValueChange={(v) => form.setData("status", v)}>
                      <SelectTrigger id="status">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="draft">Draft</SelectItem>
                        <SelectItem value="published">Published</SelectItem>
                        <SelectItem value="archived">Archived</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="type">Type</Label>
                    <Select value={form.data.type} onValueChange={(v) => form.setData("type", v)}>
                      <SelectTrigger id="type">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="simple">Simple</SelectItem>
                        <SelectItem value="variable">Variable</SelectItem>
                        <SelectItem value="digital">Digital</SelectItem>
                        <SelectItem value="service">Service</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="grid gap-2">
                  <Label>Categories</Label>
                  <div className="flex flex-wrap gap-2">
                    {categories.map((cat) => {
                      const selected = (form.data.categories || []).includes(cat.id);
                      return (
                        <button
                          key={cat.id}
                          type="button"
                          onClick={() => {
                            const current = form.data.categories || [];
                            form.setData(
                              "categories",
                              selected ? current.filter((id: number) => id !== cat.id) : [...current, cat.id],
                            );
                          }}
                          className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
                            selected
                              ? "bg-primary text-primary-foreground border-primary"
                              : "bg-muted text-muted-foreground border-input hover:bg-muted/80"
                          }`}
                        >
                          {cat.name}
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div className="flex items-center gap-6">
                  <label className="flex items-center gap-2">
                    <Switch
                      checked={form.data.is_featured}
                      onCheckedChange={(v) => form.setData("is_featured", v)}
                    />
                    <span className="text-sm">Featured</span>
                  </label>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Shipping</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-4 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="weight">Weight</Label>
                    <Input id="weight" type="number" step="0.01" min="0" value={form.data.weight} onChange={(e) => form.setData("weight", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="length">Length</Label>
                    <Input id="length" type="number" step="0.01" min="0" value={form.data.length} onChange={(e) => form.setData("length", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="width">Width</Label>
                    <Input id="width" type="number" step="0.01" min="0" value={form.data.width} onChange={(e) => form.setData("width", e.target.value)} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="height">Height</Label>
                    <Input id="height" type="number" step="0.01" min="0" value={form.data.height} onChange={(e) => form.setData("height", e.target.value)} />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Images</CardTitle>
                <CardDescription>Upload product images. First image is used as thumbnail.</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex flex-wrap gap-3">
                    {(form.data.images || []).map((img: { url: string; path: string }, i: number) => (
                      <div
                        key={i}
                        className={`relative group border rounded-lg overflow-hidden w-24 h-24 ${
                          form.data.thumbnail_url === img.url ? "ring-2 ring-primary" : ""
                        }`}
                      >
                        <img src={img.url} alt="" className="h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1">
                          <button
                            type="button"
                            onClick={() => setThumbnail(img.url)}
                            className="text-white text-xs bg-primary px-1.5 py-0.5 rounded"
                            title="Set as thumbnail"
                          >
                            Cover
                          </button>
                          <button
                            type="button"
                            onClick={() => removeImage(img.path)}
                            className="text-white text-xs bg-destructive px-1.5 py-0.5 rounded"
                            title="Remove image"
                          >
                            X
                          </button>
                        </div>
                      </div>
                    ))}
                    <label className="w-24 h-24 border-2 border-dashed rounded-lg flex items-center justify-center cursor-pointer hover:bg-muted/50 transition-colors">
                      <span className="text-xs text-muted-foreground">{uploading ? "..." : "+"}</span>
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        disabled={uploading}
                        onChange={handleImageUpload}
                      />
                    </label>
                  </div>
                </div>
              </CardContent>
            </Card>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={form.processing}>
                {form.processing ? "Saving..." : isEditing ? "Update Product" : "Create Product"}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => router.get(route("dashboard.ecommerce.products.index"))}
              >
                Cancel
              </Button>
            </div>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
