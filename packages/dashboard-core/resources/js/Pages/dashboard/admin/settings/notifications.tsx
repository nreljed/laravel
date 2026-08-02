import { Head, usePage, Link } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AdminSettingsNav } from "@/Components/settings/AdminSettingsNav";
import { NotificationSection } from "@/Components/settings/sections/NotificationSection";
import { useEffect } from "react";
import { toast } from "@/Components/ui/toast";

interface NotificationsProps {
  settings?: Record<string, string>;
}

export default function NotificationsSettings({ settings = {} }: NotificationsProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  return (
    <AdminLayout title="Settings">
      <Head title="Notification Settings" />
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
              <BreadcrumbPage>Notification Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <NotificationSection settings={settings} asAccordion />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
