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
import AiKeysSection from "@/Components/account/sections/AiKeysSection";

export default function Integrations() {
  const { props } = usePage();

  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Integrations" />

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
              <BreadcrumbPage>Integrations</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <AiKeysSection
              providers={
                props.aiProviders as Array<{
                  id: number;
                  provider: string;
                  provider_name: string;
                  masked_key: string;
                  preferred_model: string | null;
                  is_active: boolean;
                  created_at: string;
                }>
              }
              supportedProviders={
                props.supportedProviders as
                  Record<string, { name: string; models: string[] }> | undefined
              }
            />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
