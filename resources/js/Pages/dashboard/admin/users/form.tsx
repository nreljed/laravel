import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Field, FieldError, FieldGroup, FieldLabel, FieldDescription } from "@/Components/ui/field";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { PasswordInput } from "@/Components/ui/password-input";
import { Checkbox } from "@/Components/ui/checkbox";
import { Label } from "@/Components/ui/label";
import { Separator } from "@/Components/ui/separator";
import { ArrowLeft, Save, Check, X, Loader2, Shield, CalendarDays } from "lucide-react";
import { FormEventHandler, useRef, useEffect, useState } from "react";
import { cn } from "@/lib/utils";

interface Role {
  id: number;
  name: string;
  slug: string;
}

interface UserData {
  id?: number;
  username?: string;
  name: string;
  email: string;
  role_id?: number;
  email_verified_at?: string | null;
  created_at?: string;
}

interface PageProps {
  user?: UserData;
  roles: Role[];
}

export default function AdminUsersForm() {
  const { props } = usePage();
  const { user, roles } = props as unknown as PageProps;
  const isEditing = !!user?.id;

  const { data, setData, post, put, processing, errors } = useForm({
    username: user?.username ?? "",
    name: user?.name ?? "",
    email: user?.email ?? "",
    password: "",
    password_confirmation: "",
    role_id: String(user?.role_id ?? ""),
    send_email: !isEditing,
  });

  const [usernameStatus, setUsernameStatus] = useState<"idle" | "checking" | "taken" | "available">(
    "idle",
  );
  const [emailStatus, setEmailStatus] = useState<"idle" | "checking" | "taken" | "available">(
    "idle",
  );
  const usernameTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const emailTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const usernameLastChecked = useRef("");
  const emailLastChecked = useRef("");

  useEffect(() => {
    return () => {
      // eslint-disable-next-line react-hooks/exhaustive-deps
      clearTimeout(usernameTimer.current);
      // eslint-disable-next-line react-hooks/exhaustive-deps
      clearTimeout(emailTimer.current);
    };
  }, []);

  const checkAvailability = (field: "username" | "email", value: string) => {
    if (!value || value.length < 3) {
      setUsernameStatus("idle");
      setEmailStatus("idle");
      return;
    }

    const setStatus = field === "username" ? setUsernameStatus : setEmailStatus;
    const lastChecked = field === "username" ? usernameLastChecked : emailLastChecked;
    const timer = field === "username" ? usernameTimer : emailTimer;

    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      if (value === lastChecked.current) return;
      setStatus("checking");

      router.get(
        route("register.availability"),
        { field, value },
        {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page: { props: Record<string, unknown> }) => {
            const data = page.props;
            lastChecked.current = value;
            setStatus(data.available ? "available" : "taken");
          },
          onError: () => setStatus("idle"),
        },
      );
    }, 400);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (isEditing) {
      put(route("dashboard.admin.users.update", { user: user!.id }), {
        onSuccess: () => {},
      });
    } else {
      post(route("dashboard.admin.users.store"), {
        onSuccess: () => {},
      });
    }
  };

  const canSubmit = isEditing || (data.username && data.name && data.email && data.password);

  return (
    <AdminLayout title={isEditing ? "Edit User" : "Create User"}>
      <Head title={isEditing ? "Edit User" : "Create User"} />

      <div className="mb-4">
        <Button variant="ghost" size="sm" asChild>
          <Link href={route("dashboard.admin.users.index")}>
            <ArrowLeft className="mr-1 size-4" /> Back to Users
          </Link>
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>User Details</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} id="user-form">
                <FieldGroup>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Field>
                      <FieldLabel htmlFor="username">
                        Username <span className="text-destructive">*</span>
                      </FieldLabel>
                      <div className="relative">
                        <Input
                          id="username"
                          value={data.username}
                          onChange={(e) => {
                            const val = e.target.value.replaceAll(/[^a-zA-Z0-9_.-]/g, "");
                            setData("username", val);
                            if (!isEditing) checkAvailability("username", val);
                          }}
                          required
                          className={cn(
                            "pe-9",
                            !isEditing && usernameStatus === "taken" && "border-destructive",
                            !isEditing && usernameStatus === "available" && "border-green-500",
                          )}
                          aria-invalid={usernameStatus === "taken" ? "true" : undefined}
                        />
                        {!isEditing && (
                          <span className="absolute right-3 top-1/2 -translate-y-1/2">
                            {usernameStatus === "checking" && (
                              <Loader2 className="size-4 animate-spin text-muted-foreground" />
                            )}
                            {usernameStatus === "taken" && (
                              <X className="size-4 text-destructive" />
                            )}
                            {usernameStatus === "available" && (
                              <Check className="size-4 text-green-500" />
                            )}
                          </span>
                        )}
                      </div>
                      <FieldDescription>
                        Letters, numbers, underscores, hyphens and periods only.
                      </FieldDescription>
                      {usernameStatus === "taken" && !isEditing && (
                        <FieldError>This username is already taken.</FieldError>
                      )}
                      <FieldError
                        errors={errors.username ? [{ message: errors.username }] : undefined}
                      />
                    </Field>

                    <Field>
                      <FieldLabel htmlFor="name">
                        Display Name <span className="text-destructive">*</span>
                      </FieldLabel>
                      <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        required
                      />
                      <FieldError errors={errors.name ? [{ message: errors.name }] : undefined} />
                    </Field>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Field>
                      <FieldLabel htmlFor="email">
                        Email <span className="text-destructive">*</span>
                      </FieldLabel>
                      <div className="relative">
                        <Input
                          id="email"
                          type="email"
                          value={data.email}
                          onChange={(e) => {
                            setData("email", e.target.value);
                            if (!isEditing) checkAvailability("email", e.target.value);
                          }}
                          required
                          className={cn(
                            "pe-9",
                            !isEditing && emailStatus === "taken" && "border-destructive",
                            !isEditing && emailStatus === "available" && "border-green-500",
                          )}
                          aria-invalid={emailStatus === "taken" ? "true" : undefined}
                        />
                        {!isEditing && (
                          <span className="absolute right-3 top-1/2 -translate-y-1/2">
                            {emailStatus === "checking" && (
                              <Loader2 className="size-4 animate-spin text-muted-foreground" />
                            )}
                            {emailStatus === "taken" && <X className="size-4 text-destructive" />}
                            {emailStatus === "available" && (
                              <Check className="size-4 text-green-500" />
                            )}
                          </span>
                        )}
                      </div>
                      {emailStatus === "taken" && !isEditing && (
                        <FieldError>This email is already registered.</FieldError>
                      )}
                      <FieldError errors={errors.email ? [{ message: errors.email }] : undefined} />
                    </Field>

                    <Field>
                      <FieldLabel htmlFor="role">Role</FieldLabel>
                      <Select value={data.role_id} onValueChange={(v) => setData("role_id", v)}>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                          {roles.map((role) => (
                            <SelectItem key={role.id} value={String(role.id)}>
                              {role.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FieldError
                        errors={errors.role_id ? [{ message: errors.role_id }] : undefined}
                      />
                    </Field>
                  </div>

                  <Separator />

                  <div>
                    <FieldLabel className="mb-3">
                      {isEditing ? "New Password (leave blank to keep)" : "Password"}{" "}
                      <span className="text-destructive">*</span>
                    </FieldLabel>
                    <PasswordInput
                      variant="register"
                      layout="inline"
                      newPasswordLabel={isEditing ? "New Password" : "Password"}
                      confirmLabel="Confirm Password"
                      showStrengthBar
                      showInfoIcon={!isEditing}
                      newPasswordValue={data.password}
                      onNewPasswordChange={(e) => setData("password", e.target.value)}
                      confirmValue={data.password_confirmation}
                      onConfirmChange={(e) => setData("password_confirmation", e.target.value)}
                      newPasswordError={errors.password}
                      confirmError={errors.password_confirmation}
                      required={!isEditing}
                    />
                  </div>

                  <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" asChild>
                      <Link href={route("dashboard.admin.users.index")}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing || !canSubmit}>
                      <Save className="mr-1 size-4" /> {isEditing ? "Update User" : "Create User"}
                    </Button>
                  </div>
                </FieldGroup>
              </form>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Shield className="size-4" />
                Role & Status
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {isEditing && user && (
                <>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Email verified</span>
                    {user.email_verified_at ? (
                      <Badge className="bg-green-600 flex items-center gap-1">
                        <Check className="size-3" /> Verified
                      </Badge>
                    ) : (
                      <Badge variant="secondary" className="flex items-center gap-1">
                        <X className="size-3" /> Unverified
                      </Badge>
                    )}
                  </div>

                  {user.created_at && (
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">Created</span>
                      <span className="text-sm flex items-center gap-1">
                        <CalendarDays className="size-3.5 text-muted-foreground" />
                        {new Date(user.created_at).toLocaleDateString(undefined, {
                          year: "numeric",
                          month: "short",
                          day: "numeric",
                        })}
                      </span>
                    </div>
                  )}
                </>
              )}

              {!isEditing && (
                <>
                  <p className="text-sm text-muted-foreground">
                    Fill in the user details to create a new account.
                  </p>

                  <Separator />

                  <label className="flex items-start gap-3 cursor-pointer">
                    <Checkbox
                      checked={data.send_email}
                      onCheckedChange={(v) => setData("send_email", !!v)}
                      id="send_email"
                    />
                    <div className="flex flex-col gap-0.5">
                      <Label
                        htmlFor="send_email"
                        className="text-sm font-medium leading-none cursor-pointer"
                      >
                        Notify user via email
                      </Label>
                      <span className="text-xs text-muted-foreground">
                        Send a password reset link so they can set their own password.
                      </span>
                    </div>
                  </label>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
