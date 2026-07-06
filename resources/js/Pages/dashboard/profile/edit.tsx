import { useState, useMemo, useCallback, useEffect, useRef, startTransition } from "react";
import { Link, Head, router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import { User, Building2, ShoppingCart, Loader2, LucideIcon } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";

import { AccountInfoSection } from "@/Components/profile/sections/AccountInfoSection";
import { AvatarSection } from "@/Components/profile/sections/AvatarSection";
import { PersonalInfoSection } from "@/Components/profile/sections/PersonalInfoSection";
import { ContactInfoSection } from "@/Components/profile/sections/ContactInfoSection";

interface TabConfig {
  value: string;
  label: string;
  icon: LucideIcon;
}

const CORE_TABS: TabConfig[] = [{ value: "general", label: "General", icon: User }];

export default function ProfileEdit() {
  const { props } = usePage();
  const authUser = (props.auth as Record<string, unknown>)?.user as
    Record<string, unknown> | undefined;
  const serverUser = props.user as Record<string, unknown> | undefined;
  const status = props.status as string | undefined;
  const modulesData = props.modules as { enabled: string[] } | undefined;

  const enabled = modulesData?.enabled ?? [];

  const hasProperties = enabled.includes("properties");
  const hasBookings = enabled.includes("bookings");
  const hasShop = enabled.includes("shop");

  const allTabs = useMemo(() => {
    const tabs = [...CORE_TABS];
    if (hasProperties) tabs.push({ value: "properties", label: "Properties", icon: Building2 });
    if (hasBookings) tabs.push({ value: "bookings", label: "Bookings", icon: Building2 });
    if (hasShop) tabs.push({ value: "shop", label: "Shop", icon: ShoppingCart });
    return tabs;
  }, [hasProperties, hasBookings, hasShop]);

  const [activeTab, setActiveTab] = useState(() => {
    const params = new URLSearchParams(location.search);
    const tab = params.get("tab");
    if (tab && allTabs.some((t) => t.value === tab)) return tab;
    return "general";
  });

  useEffect(() => {
    const params = new URLSearchParams(location.search);
    const tab = params.get("tab");
    const valid = tab && allTabs.some((t) => t.value === tab);
    if (valid && tab !== activeTab) startTransition(() => setActiveTab(tab!));
  }, [allTabs, activeTab]);

  const handleTabChange = useCallback((value: string) => {
    setActiveTab(value);
    const url = new URL(location.href);
    url.searchParams.set("tab", value);
    history.replaceState({}, "", url);
  }, []);

  const user = useMemo(() => {
    const u = serverUser || {};
    const auth = authUser || {};
    return {
      ...auth,
      ...u,
      id: (u.id as string) || (auth.id as string),
      user_login: (u.name as string) || (auth.name as string),
      user_email: (u.email as string) || (auth.email as string),
      user_registered: (u.user_registered as string) || (auth.user_registered as string),
      display_name:
        (u.display_name as string) || (auth.display_name as string) || (auth.name as string),
      display_name_options:
        (u.display_name_options as Record<string, unknown>) ||
        (auth.display_name_options as Record<string, unknown>) ||
        {},
      nickname: (u.nickname as string) || (auth.nickname as string),
      first_name: (u.first_name as string) || (auth.first_name as string),
      last_name: (u.last_name as string) || (auth.last_name as string),
      description:
        (u.description as string) ||
        (u.bio as string) ||
        (auth.description as string) ||
        (auth.bio as string),
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
        nickname: (data.nickname as string) || "",
        first_name: (data.first_name as string) || "",
        last_name: (data.last_name as string) || "",
        url: (data.url as string) || (data.website as string) || "",
        description: (data.description as string) || (data.bio as string) || "",
        contact_email: (data.contact_email as string) || "",
      };
    },
    [user],
  );

  const initialFormData = useMemo(() => {
    const u = user as Record<string, unknown>;
    const hasUserData = u && (u.id || u.user_login || u.display_name || u.nickname);
    if (hasUserData) return buildFormData();
    return null;
  }, [buildFormData, user]);

  const [uploading, setUploading] = useState(false);
  const [avatarError, setAvatarError] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record<string, unknown> | null>(initialFormData);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const isLoading = formData === null;

  useEffect(() => {
    if (status) toast.success(status);
  }, [status]);

  const handleFieldChange = useCallback((field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  }, []);

  const handlePersonalInfoSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (!formData) return;
      const payload: Record<string, string> = {};
      for (const [key, value] of Object.entries(formData)) {
        if (typeof value === "string") {
          payload[key] = value;
        }
      }
      payload._update_type = "personal-info";
      router.patch(route("dashboard.profile.update"), payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: (page: unknown) => {
          const pg = page as { props: Record<string, unknown> };
          const freshUser = pg.props.user as Record<string, unknown> | undefined;
          if (freshUser) setFormData(buildFormData(freshUser));
          toast.success("Profile updated successfully!");
        },
        onError: (errors) => {
          setFormErrors(errors as Record<string, string>);
          toast.error("Failed to update profile.");
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

      await new Promise<void>((resolve, reject) => {
        router.post(route("dashboard.profile.avatar.upload"), formData, {
          preserveState: true,
          onSuccess: (page: { props: Record<string, unknown> }) => {
            const result = page.props;
            const avatar = result.avatar as { url?: string } | undefined;
            if (avatar?.url) {
              setAvatarPreview(avatar.url);
            }
            router.reload({ only: ["auth"], replace: true });
            resolve();
          },
          onError: (errors: Record<string, string>) => {
            reject(new Error(errors.error || "Upload failed"));
          },
        });
      });
    } catch (error) {
      const msg = (error as Error).message || "Upload failed. Please try again.";
      setAvatarError(msg);
      toast.error(msg);
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
      await new Promise<void>((resolve, reject) => {
        router.delete(route("dashboard.profile.avatar.destroy"), {
          preserveState: true,
          onSuccess: () => {
            setAvatarPreview(null);
            router.reload({ only: ["auth"], replace: true });
            toast.success("Avatar removed successfully!");
            resolve();
          },
          onError: (errors: Record<string, string>) => {
            reject(new Error(errors.error || "Failed to remove avatar."));
          },
        });
      });
    } catch (error) {
      toast.error((error as Error).message || "Failed to remove avatar.");
    } finally {
      setUploading(false);
    }
  }, []);

  if (isLoading) {
    return (
      <AuthenticatedLayout header="Profile" maxWidth="max-w-4xl">
        <Head title="Profile" />
        <div className="space-y-6">
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbLink asChild>
                  <Link href="/dashboard">Dashboard</Link>
                </BreadcrumbLink>
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
    <AuthenticatedLayout header="Profile" maxWidth="max-w-4xl">
      <Head title="Profile" />

      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <Link href="/dashboard">Dashboard</Link>
              </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Profile</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <Tabs value={activeTab} onValueChange={handleTabChange}>
          <TabsList className="!h-auto flex-wrap gap-1.5 p-1.5">
            {allTabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <TabsTrigger
                  key={tab.value}
                  value={tab.value}
                  className="shrink-0 gap-2 px-3 py-2 text-sm"
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  <span>{tab.label}</span>
                </TabsTrigger>
              );
            })}
          </TabsList>

          <TabsContent value="general" className="mt-6 space-y-6">
            <AccountInfoSection
              user={user as Record<string, unknown>}
              roles={roles}
              asAccordion
              accordionDefaultOpen
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
              onSubmit={handlePersonalInfoSubmit}
              asAccordion
              accordionDefaultOpen={false}
            />

            <ContactInfoSection
              data={user as Record<string, unknown>}
              asAccordion
              accordionDefaultOpen={false}
            />
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
