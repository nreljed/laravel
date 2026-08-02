import { Head, Link, useForm, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Checkbox } from "@/Components/ui/checkbox";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/Components/ui/field";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { ArrowLeft, Lock, Save, ShieldAlert } from "lucide-react";
import { FormEventHandler } from "react";

interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string;
  assigned: boolean;
}

interface PermissionGroup {
  group: string;
  permissions: Permission[];
}

interface RoleData {
  id?: number;
  name: string;
  slug?: string;
  description: string | null;
}

interface PageProps {
  role: RoleData | null;
  permissionGroups: PermissionGroup[];
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replaceAll(/[^a-z0-9]+/g, "_")
    .replaceAll(/^_|_$/g, "")
    .replaceAll(/_+/g, "_");
}

export default function AdminRolesForm() {
  const { props } = usePage();
  const { role, permissionGroups } = props as unknown as PageProps;
  const isEditing = !!role?.id;
  const isAdminRole = role?.slug === "admin";

  const initialPermissionIds = permissionGroups
    .flatMap((g) => g.permissions)
    .filter((p) => p.assigned)
    .map((p) => p.id);

  const { data, setData, post, put, processing, errors } = useForm({
    name: role?.name ?? "",
    slug: role?.slug ?? "",
    description: role?.description ?? "",
    permission_ids: initialPermissionIds,
  });

  const handleNameChange = (value: string) => {
    setData("name", value);
    if (!isEditing) {
      setData("slug", slugify(value));
    }
  };

  const togglePermission = (permId: number) => {
    const current = data.permission_ids;
    if (current.includes(permId)) {
      setData(
        "permission_ids",
        current.filter((id) => id !== permId),
      );
    } else {
      setData("permission_ids", [...current, permId]);
    }
  };

  const toggleGroup = (permissions: Permission[], assigned: boolean) => {
    const ids = permissions.map((p) => p.id);
    if (assigned) {
      setData(
        "permission_ids",
        data.permission_ids.filter((id) => !ids.includes(id)),
      );
    } else {
      const existing = new Set(data.permission_ids);
      for (const id of ids) existing.add(id);
      setData("permission_ids", [...existing]);
    }
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (isEditing) {
      put(route("dashboard.admin.roles.update", { role: role!.id }));
    } else {
      post(route("dashboard.admin.roles.store"));
    }
  };

  return (
    <AdminLayout title={isEditing ? `Edit: ${role!.name}` : "Create Role"}>
      <Head title={isEditing ? `Edit: ${role!.name}` : "Create Role"} />

      <div className="mb-4">
        <Link href={route("dashboard.admin.roles.index")}>
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-1 size-4" /> Back to Roles
          </Button>
        </Link>
      </div>

      {isAdminRole && (
        <Alert className="mb-6 border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
          <ShieldAlert className="h-5 w-5 text-amber-600 dark:text-amber-400" />
          <AlertDescription className="text-amber-700 dark:text-amber-300 ml-2">
            The <strong>Admin</strong> role permissions are locked to prevent accidental
            self-lockout. To change admin permissions, edit individual permissions or use the
            Permissions Manager.
          </AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card className="lg:col-span-1">
          <CardHeader>
            <CardTitle>Role Details</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} id="role-form">
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
                    required={!isEditing}
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

                <div className="pt-2">
                  <Button type="submit" disabled={processing || isAdminRole} className="w-full">
                    {isAdminRole ? (
                      <Lock className="mr-1 size-4" />
                    ) : (
                      <Save className="mr-1 size-4" />
                    )}
                    {isAdminRole ? "Admin Locked" : isEditing ? "Save Role" : "Create Role"}
                  </Button>
                </div>
              </FieldGroup>
            </form>
          </CardContent>
        </Card>

        <div className="lg:col-span-2 space-y-4">
          {permissionGroups.map((group) => {
            const allAssigned = group.permissions.every((p) => data.permission_ids.includes(p.id));

            return (
              <Card key={group.group}>
                <CardHeader className="py-3">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-base">{group.group}</CardTitle>
                    {!isAdminRole && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => toggleGroup(group.permissions, allAssigned)}
                      >
                        {allAssigned ? "Deselect All" : "Select All"}
                      </Button>
                    )}
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {group.permissions.map((perm) => (
                      <label
                        key={perm.id}
                        className="flex items-start gap-3 rounded-md border p-3 cursor-pointer hover:bg-accent transition-colors has-data-[checked]:border-primary"
                      >
                        {isAdminRole ? (
                          <div className="flex items-center gap-3 w-full">
                            <Lock className="h-4 w-4 text-muted-foreground shrink-0" />
                            <div className="flex flex-col gap-0.5">
                              <span className="text-sm font-medium leading-none">{perm.name}</span>
                              {perm.description && (
                                <span className="text-xs text-muted-foreground">
                                  {perm.description}
                                </span>
                              )}
                            </div>
                          </div>
                        ) : (
                          <>
                            <Checkbox
                              checked={data.permission_ids.includes(perm.id)}
                              onCheckedChange={() => togglePermission(perm.id)}
                            />
                            <div className="flex flex-col gap-0.5">
                              <span className="text-sm font-medium leading-none">{perm.name}</span>
                              {perm.description && (
                                <span className="text-xs text-muted-foreground">
                                  {perm.description}
                                </span>
                              )}
                            </div>
                          </>
                        )}
                      </label>
                    ))}
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </div>
    </AdminLayout>
  );
}
