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
import { SecuritySection } from "@/Components/settings/sections/SecuritySection";
import { useEffect } from "react";
import { toast } from "@/Components/ui/toast";

interface SecurityProps {
  settings?: Record<string, string>;
}

export default function SecuritySettings({ settings = {} }: SecurityProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  return (
    <AdminLayout title="Settings">
      <Head title="Security Settings" />
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
