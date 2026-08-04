import { Head, Link, useForm, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/Components/ui/field";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxLabel,
  ComboboxList,
  ComboboxValue,
} from "@/Components/ui/combobox";
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

interface GroupedPermissions {
  group: string;
  items: Permission[];
}

interface RoleData {
  id?: number;
  name: string;
  slug?: string;
  description: string | null;
  is_system?: boolean;
  is_default?: boolean;
}

interface RoleOption {
  id: number;
  name: string;
  slug: string;
  is_system: boolean;
  permission_ids: number[];
}

interface PageProps {
  role: RoleData | null;
  permissionGroups: PermissionGroup[];
  roles?: RoleOption[];
  defaultRoleId?: number | null;
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replaceAll(/[^a-z0-9]+/g, "-")
    .replaceAll(/^-+|-+$/g, "")
    .replaceAll(/-+/g, "-");
}

export default function AdminRolesForm() {
  const { props } = usePage();
  const { role, permissionGroups, roles = [] } = props as unknown as PageProps;
  const isEditing = !!role?.id;
  const isLocked = isEditing && role?.is_system === true;

  const groupedPermissions = permissionGroups.map((group) => ({
    group: group.group,
    items: group.permissions,
  }));

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

  const selectedPermissions = permissionGroups
    .flatMap((g) => g.permissions)
    .filter((p) => data.permission_ids.includes(p.id));

  const handleNameChange = (value: string) => {
    setData("name", value);
    if (!isEditing) {
      setData("slug", slugify(value));
    }
  };

  const handleBaseRoleChange = (value: string | null) => {
    if (!value) return;
    const base = roles.find((r) => String(r.id) === value);
    if (base) {
      setData("permission_ids", base.permission_ids);
    }
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (isLocked) return;
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

      {isLocked && (
        <Alert className="mb-6 border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
          <ShieldAlert className="h-5 w-5 text-amber-600 dark:text-amber-400" />
          <AlertDescription className="text-amber-700 dark:text-amber-300 ml-2">
            <strong>{role!.name}</strong> is a system role managed by the config catalog and is
            read-only here to prevent accidental lockout. To change its permissions, edit the
            catalog in <code className="text-xs">config/roles.php</code> or use the Permissions
            Manager for custom roles.
          </AlertDescription>
        </Alert>
      )}

      <div className="mx-auto max-w-3xl">
        <Card>
          <CardHeader>
            <CardTitle>Role Details</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} id="role-form">
              <FieldGroup>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Field>
                    <FieldLabel htmlFor="name">Name</FieldLabel>
                    <Input
                      id="name"
                      value={data.name}
                      onChange={(e) => handleNameChange(e.target.value)}
                      required
                      disabled={isLocked}
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
                      disabled={isEditing || isLocked}
                      className="font-mono text-sm"
                    />
                    <FieldError errors={errors.slug ? [{ message: errors.slug }] : undefined} />
                  </Field>
                </div>

                <Field>
                  <FieldLabel htmlFor="description">Description</FieldLabel>
                  <Textarea
                    id="description"
                    value={data.description}
                    onChange={(e) => setData("description", e.target.value)}
                    rows={3}
                    disabled={isLocked}
                  />
                  <FieldError
                    errors={errors.description ? [{ message: errors.description }] : undefined}
                  />
                </Field>

                {!isEditing && (
                  <Field>
                    <FieldLabel htmlFor="base-role">Start from</FieldLabel>
                    <Select onValueChange={handleBaseRoleChange}>
                      <SelectTrigger className="w-full" aria-label="Start from an existing role">
                        <SelectValue placeholder="Start with an empty role">
                          {(value: string | null) =>
                            value
                              ? (roles.find((r) => String(r.id) === value)?.name ?? "Select a role")
                              : "Start with an empty role"
                          }
                        </SelectValue>
                      </SelectTrigger>
                      <SelectContent>
                        {roles.map((r) => (
                          <SelectItem key={r.id} value={String(r.id)}>
                            {r.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FieldDescription>
                      Pre-selects the same permissions as an existing role. You can adjust them
                      below.
                    </FieldDescription>
                  </Field>
                )}

                <Field>
                  <FieldLabel htmlFor="permissions">Permissions</FieldLabel>
                  <Combobox
                    multiple
                    disabled={isLocked}
                    value={selectedPermissions}
                    onValueChange={(permissions) =>
                      setData(
                        "permission_ids",
                        (permissions as Permission[]).map((p) => p.id),
                      )
                    }
                    items={groupedPermissions}
                    itemToStringLabel={(permission) => permission.name}
                    itemToStringValue={(permission) => String(permission.id)}
                  >
                    <ComboboxChips className="w-full" data-slot="combobox-chips-field">
                      <ComboboxValue>
                        {(value: Permission[]) => (
                          <>
                            {value.map((permission) => (
                              <ComboboxChip key={permission.id} showRemove={!isLocked}>
                                {permission.name}
                              </ComboboxChip>
                            ))}
                            <ComboboxChipsInput
                              placeholder={
                                value.length === 0 ? "Search and select permissions..." : ""
                              }
                            />
                          </>
                        )}
                      </ComboboxValue>
                    </ComboboxChips>
                    <ComboboxContent className="w-full">
                      <ComboboxEmpty>No permissions found.</ComboboxEmpty>
                      <ComboboxList>
                        {(group: GroupedPermissions) => (
                          <ComboboxGroup key={group.group} items={group.items}>
                            <ComboboxLabel>{group.group}</ComboboxLabel>
                            {group.items.map((permission) => (
                              <ComboboxItem key={permission.id} value={permission}>
                                {permission.name}
                              </ComboboxItem>
                            ))}
                          </ComboboxGroup>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                  <FieldError
                    errors={
                      errors.permission_ids ? [{ message: errors.permission_ids }] : undefined
                    }
                  />
                  <FieldDescription>
                    {data.permission_ids.length} permission
                    {data.permission_ids.length === 1 ? "" : "s"} selected
                  </FieldDescription>
                </Field>

                <div className="pt-2 flex items-center justify-end gap-3">
                  <Button type="submit" disabled={processing || isLocked}>
                    {isLocked ? <Lock className="mr-1 size-4" /> : <Save className="mr-1 size-4" />}
                    {isLocked ? "System Locked" : isEditing ? "Save Role" : "Create Role"}
                  </Button>
                </div>
              </FieldGroup>
            </form>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
