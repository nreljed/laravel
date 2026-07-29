import { Link, Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AccountSettingsNav } from "@/Components/account/AccountSettingsNav";
import { DangerZoneSection } from "@/Components/account/sections/DangerZoneSection";

export default function Advanced() {
  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Advanced" />

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
              <BreadcrumbPage>Advanced</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <DangerZoneSection asAccordion accordionDefaultOpen={false} />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
