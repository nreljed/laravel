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
import { SecuritySection } from "../../../../Components/settings/sections/SecuritySection";

interface SecurityProps {
  settings?: Record<string, string>;
}

export default function SecuritySettings({ settings = {} }: SecurityProps) {
  return (
    <AdminLayout title="Settings">
      <Head title="Security Settings" />
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
              <BreadcrumbPage>Security Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <SecuritySection settings={settings} asAccordion />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
