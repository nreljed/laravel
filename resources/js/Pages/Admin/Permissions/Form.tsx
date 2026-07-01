import { Head, Link, useForm, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/Components/ui/field";
import { ArrowLeft, Save } from "lucide-react";
import { FormEventHandler } from "react";

interface PermissionData {
  id?: number;
  name: string;
  slug: string;
  description: string | null;
  guard_name: string;
}

interface PageProps {
  permission: PermissionData | null;
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replaceAll(/[^a-z0-9.]+/g, "-")
    .replaceAll(/^-|-$/g, "")
    .replaceAll(/-+/g, "-");
}

export default function AdminPermissionsForm() {
  const { props } = usePage();
  const { permission } = props as unknown as PageProps;
  const isEditing = !!permission?.id;

  const { data, setData, post, put, processing, errors } = useForm({
    name: permission?.name ?? "",
    slug: permission?.slug ?? "",
    description: permission?.description ?? "",
    guard_name: permission?.guard_name ?? "web",
  });

  const handleNameChange = (value: string) => {
    setData("name", value);
    if (!isEditing) {
      setData("slug", slugify(value));
    }
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (isEditing) {
      put(route("dashboard.admin.permissions.update", { permission: permission!.id }), {
        onSuccess: () => {},
      });
    } else {
      post(route("dashboard.admin.permissions.store"), {
        onSuccess: () => {},
      });
    }
  };

  return (
    <AdminLayout title={isEditing ? `Edit: ${permission!.name}` : "Create Permission"}>
      <Head title={isEditing ? `Edit: ${permission!.name}` : "Create Permission"} />

      <div className="mb-4">
        <Button variant="ghost" size="sm" asChild>
          <Link href={route("dashboard.admin.permissions.index")}>
            <ArrowLeft className="mr-1 size-4" /> Back to Permissions
          </Link>
        </Button>
      </div>

      <Card className="max-w-lg">
        <CardHeader>
          <CardTitle>{isEditing ? "Edit Permission" : "Create Permission"}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit}>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="name">Name</FieldLabel>
                <Input
                  id="name"
                  value={data.name}
                  onChange={(e) => handleNameChange(e.target.value)}
                  required
                />
                <FieldError errors={errors.name ? [{ message: errors.name }] : undefined} />
              </Field>

              <Field>
                <FieldLabel htmlFor="slug">Slug</FieldLabel>
                <Input
                  id="slug"
                  value={data.slug}
                  onChange={(e) => setData("slug", e.target.value)}
                  required
                  disabled={isEditing}
                  className="font-mono text-sm"
                />
                <FieldError errors={errors.slug ? [{ message: errors.slug }] : undefined} />
              </Field>

              <Field>
                <FieldLabel htmlFor="description">Description</FieldLabel>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData("description", e.target.value)}
                  rows={3}
                />
                <FieldError
                  errors={errors.description ? [{ message: errors.description }] : undefined}
                />
              </Field>

              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" asChild>
                  <Link href={route("dashboard.admin.permissions.index")}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                  <Save className="mr-1 size-4" />{" "}
                  {isEditing ? "Update Permission" : "Create Permission"}
                </Button>
              </div>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
