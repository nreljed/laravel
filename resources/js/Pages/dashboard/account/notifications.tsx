import { Link, Head, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AccountSettingsNav } from "@/Components/account/AccountSettingsNav";
import { NotificationPrefsSection } from "@/Components/account/sections/NotificationPrefsSection";

export default function Notifications() {
  const { props } = usePage();

  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Notifications" />

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
              <BreadcrumbPage>Notifications</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <NotificationPrefsSection
              notificationTypes={
                props.notificationTypes as Record<
                  string,
                  Record<
                    string,
                    {
                      label: string;
                      description: string;
                      default_channels: string[];
                      default_enabled: boolean;
                      group: string;
                    }
                  >
                >
              }
              preferences={
                props.preferences as Record<string, { in_app?: boolean; email?: boolean }>
              }
            />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
