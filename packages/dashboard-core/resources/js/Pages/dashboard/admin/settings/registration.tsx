import { Head, Link } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AdminSettingsNav } from "@/Components/settings/AdminSettingsNav";
import { RegistrationSection } from "@/Components/settings/sections/RegistrationSection";

interface RegistrationProps {
  settings?: Record<string, string>;
  roles?: { id: number; name: string; slug: string }[];
}

export default function RegistrationSettings({ settings = {}, roles = [] }: RegistrationProps) {
  return (
    <AdminLayout title="Settings">
      <Head title="Registration Settings" />
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
              <BreadcrumbPage>Registration Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <RegistrationSection settings={settings} roles={roles} asAccordion />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
