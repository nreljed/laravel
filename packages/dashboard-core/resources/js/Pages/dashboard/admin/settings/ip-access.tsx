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
import { IpAccessSection } from "@/Components/settings/sections/IpAccessSection";
import { useEffect } from "react";
import { toast } from "@/Components/ui/toast";

interface IpAccessProps {
  ipRules?: {
    id: number;
    ip_address: string;
    type: "allow" | "block";
    description: string | null;
    is_active: boolean;
  }[];
}

export default function IpAccessSettings({ ipRules = [] }: IpAccessProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  return (
    <AdminLayout title="Settings">
      <Head title="IP Access Settings" />
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
              <BreadcrumbPage>IP Access Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <IpAccessSection ipRules={ipRules} asAccordion />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
