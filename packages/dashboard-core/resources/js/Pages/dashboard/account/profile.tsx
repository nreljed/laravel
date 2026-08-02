import { useState, useMemo, useCallback, useEffect, useRef } from "react";
import { Link, Head, router, usePage } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Loader2 } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AccountSettingsNav } from "@/Components/account/AccountSettingsNav";
import { AccountInfoSection } from "@/Components/account/sections/AccountInfoSection";
import { AvatarSection } from "@/Components/account/sections/AvatarSection";
import { PersonalInfoSection } from "@/Components/account/sections/PersonalInfoSection";
import { ContactInfoSection } from "@/Components/account/sections/ContactInfoSection";

export default function Profile() {
  const { props } = usePage();
  const authUser = (props.auth as Record<string, unknown>)?.user as
    Record<string, unknown> | undefined;
  const serverUser = props.user as Record<string, unknown> | undefined;
  const status = props.status as string | undefined;

  const user = useMemo(() => {
    const u = serverUser || {};
    const auth = authUser || {};
    return {
      ...auth,
      ...u,
      id: (u.id as string) || (auth.id as string),
      user_login:
        (u.username as string) ||
        (auth.username as string) ||
        (u.name as string) ||
        (auth.name as string),
      user_email: (u.email as string) || (auth.email as string),
      user_registered: (u.user_registered as string) || (auth.user_registered as string),
      display_name:
        (u.display_name as string) || (auth.display_name as string) || (auth.name as string),
      display_name_options:
        (u.display_name_options as Record<string, unknown>) ||
        (auth.display_name_options as Record<string, unknown>) ||
        {},
      first_name: (u.first_name as string) || (auth.first_name as string),
      last_name: (u.last_name as string) || (auth.last_name as string),
      bio: (u.bio as string) || (auth.bio as string),
      url: (u.url as string) || (auth.url as string) || "",
      avatar: (auth.avatar as string) || (u.avatar as string),
    };
  }, [serverUser, authUser]);

  const roles = (authUser?.roles as string[]) || (serverUser?.roles as string[]) || [];

  const [avatarPreview, setAvatarPreview] = useState<string | null>(() => {
    const u = serverUser || {};
    const a = authUser || {};
    return (
      (u.custom_avatar_url as string) ||
      (u.avatar as string) ||
      (a.custom_avatar_url as string) ||
      (a.avatar as string) ||
      null
    );
  });
  const hasCustomAvatar = !!(avatarPreview && avatarPreview.includes("/avatars/"));
  const currentUploadIdRef = useRef<string | null>(null);

  const buildFormData = useCallback(
    (userData?: Record<string, unknown>) => {
      const data = userData || (user as Record<string, unknown>) || {};
      return {
        name: (data.name as string) || (data.user_login as string) || "",
        display_name: (data.display_name as string) || (data.name as string) || "",
        display_name_options: (data.display_name_options as Record<string, string>) || {},
        first_name: (data.first_name as string) || "",
        last_name: (data.last_name as string) || "",
        url: (data.url as string) || (data.website as string) || "",
        bio: (data.bio as string) || "",
        contact_email: (data.contact_email as string) || "",
      };
    },
    [user],
  );

  const initialFormData = useMemo(() => {
    const u = user as Record<string, unknown>;
    const hasUserData = u && (u.id || u.user_login || u.display_name);
    if (hasUserData) return buildFormData();
    return null;
  }, [buildFormData, user]);

  const [uploading, setUploading] = useState(false);
  const [avatarError, setAvatarError] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record<string, unknown> | null>(initialFormData);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const isLoading = formData === null;

  useEffect(() => {
    if (status) toast.add({ title: status, type: "success" });
  }, [status]);

  const handleFieldChange = useCallback((field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  }, []);

  const handleProfileSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (!formData) return;
      const payload: Record<string, string> = {};
      for (const [key, value] of Object.entries(formData)) {
        if (typeof value === "string") {
          payload[key] = value;
        }
      }
      payload._update_type = "profile";
      router.patch(route("dashboard.account.profile"), payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: (page: unknown) => {
          const pg = page as { props: Record<string, unknown> };
          const freshUser = pg.props.user as Record<string, unknown> | undefined;
          if (freshUser) setFormData(buildFormData(freshUser));
          toast.add({ title: "Profile updated successfully!", type: "success" });
        },
        onError: (errors) => {
          setFormErrors(errors as Record<string, string>);
          toast.add({ title: "Failed to update profile.", type: "error" });
        },
      });
    },
    [formData, buildFormData],
  );

  const handleAvatarUpload = useCallback(async (processedFile: File | Blob, uploadId: string) => {
    const filename = `avatar_${Date.now()}.webp`;
    currentUploadIdRef.current = uploadId;
    setAvatarError(null);
    setUploading(true);

    try {
      const file =
        processedFile instanceof File
          ? processedFile
          : new File([processedFile], filename, { type: "image/webp" });

      const formData = new FormData();
      formData.append("avatar", file);

      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      const response = await fetch(route("dashboard.account.avatar.upload"), {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: formData,
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Upload failed");
      }

      if (result.avatar?.url) {
        setAvatarPreview(result.avatar.url);
      }
      router.reload({ only: ["auth"], replace: true });
    } catch (error) {
      const msg = (error as Error).message || "Upload failed. Please try again.";
      setAvatarError(msg);
      toast.add({ title: msg, type: "error" });
    } finally {
      if (currentUploadIdRef.current === uploadId) {
        currentUploadIdRef.current = null;
      }
      setUploading(false);
    }
  }, []);

  const handleDeleteAvatar = useCallback(async (_deleteId: string) => {
    setUploading(true);

    try {
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      const response = await fetch(route("dashboard.account.avatar.destroy"), {
        method: "DELETE",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrfToken,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Failed to remove avatar.");
      }

      setAvatarPreview(null);
      router.reload({ only: ["auth"], replace: true });
      toast.add({ title: "Avatar removed successfully!", type: "success" });
    } catch (error) {
      toast.add({ title: (error as Error).message || "Failed to remove avatar.", type: "error" });
    } finally {
      setUploading(false);
    }
  }, []);

  if (isLoading) {
    return (
      <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
        <Head title="Profile" />
        <div className="space-y-6">
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <Link href="/dashboard" className="transition-colors hover:text-foreground">
                  Dashboard
                </Link>
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbPage>Profile</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Profile" />

      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link href="/dashboard" className="transition-colors hover:text-foreground">
                Dashboard
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Account Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <AccountInfoSection
              user={user as Record<string, unknown>}
              roles={roles}
              asAccordion
              accordionDefaultOpen={false}
            />

            <AvatarSection
              currentAvatar={avatarPreview}
              hasCustomAvatar={hasCustomAvatar}
              onUpload={handleAvatarUpload}
              onDelete={handleDeleteAvatar}
              uploading={uploading}
              error={avatarError}
              userName={(user.display_name as string) || ""}
              asAccordion
              accordionDefaultOpen={false}
            />

            <PersonalInfoSection
              data={formData as Record<string, unknown>}
              onChange={handleFieldChange}
              errors={formErrors}
              loading={false}
              onSubmit={handleProfileSubmit}
              asAccordion
              accordionDefaultOpen={false}
            />

            <ContactInfoSection
              data={user as Record<string, unknown>}
              asAccordion
              accordionDefaultOpen={false}
            />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
