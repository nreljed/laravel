import { Head, Link } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../../Components/ui/breadcrumb";
import { AdminSettingsNav } from "../../../../Components/settings/AdminSettingsNav";
import { NotificationSection } from "../../../../Components/settings/sections/NotificationSection";
import { DevelopmentNotificationTest } from "../../../../Components/settings/sections/DevelopmentNotificationTest";

interface NotificationType {
  label: string;
  description?: string;
  default_channels?: string[];
  default_enabled?: boolean;
  required?: boolean;
  group?: string;
  severity?: string;
}

interface NotificationsProps {
  settings?: Record<string, string>;
  notificationTableExists?: boolean;
  notificationTypes?: Record<string, Record<string, NotificationType>>;
}

export default function NotificationsSettings({
  settings = {},
  notificationTableExists = false,
  notificationTypes = {},
}: NotificationsProps) {
  return (
    <AdminLayout title="Settings">
      <Head title="Notification Settings" />
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link
                href={route("dashboard.index")}
                className="transition-colors hover:text-foreground"
              >
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
            <DevelopmentNotificationTest
              notificationTableExists={notificationTableExists}
              notificationTypes={notificationTypes}
            />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
