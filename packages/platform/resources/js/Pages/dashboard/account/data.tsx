import { useEffect } from "react";
import { Link, Head, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "../../../Components/ui/breadcrumb";
import { AccountSettingsNav } from "../../../Components/account/AccountSettingsNav";
import { DataBackupSection, type DataModule } from "../../../Components/account/sections/DataBackupSection";
import { toast } from "../../../Components/ui/toast";

export default function AccountData() {
  const { props } = usePage();
  const status = props.status as string | undefined;

  useEffect(() => {
    if (status) toast.add({ title: status, type: "success" });
  }, [status]);

  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Backup & Restore" />

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
              <BreadcrumbPage>Backup &amp; Restore</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <div>
              <h2 className="text-xl font-semibold tracking-tight">Backup &amp; Restore</h2>
              <p className="text-sm text-muted-foreground mt-1">
                Export a copy of your own data from modules that support backup, and restore it
                later from a file. Host-level database backups are handled by your hosting
                provider — this is your self-service export.
              </p>
            </div>
            <DataBackupSection modules={(props.modules as DataModule[]) ?? []} />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}