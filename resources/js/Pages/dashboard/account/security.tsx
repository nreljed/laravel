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
import { EmailSection } from "@/Components/account/sections/EmailSection";
import { PasswordSection } from "@/Components/account/sections/PasswordSection";

export default function Security() {
  const { props } = usePage();
  const status = props.status as string | undefined;
  const user = props.user as Record<string, unknown> | undefined;

  return (
    <AuthenticatedLayout header="Account Settings" maxWidth="max-w-5xl">
      <Head title="Security" />

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
              <BreadcrumbPage>Security</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col md:flex-row gap-8">
          <AccountSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <EmailSection
              user={user}
              mustVerifyEmail={props.mustVerifyEmail as boolean}
              initialStatus={status}
              asAccordion
              accordionDefaultOpen={false}
            />

            <PasswordSection asAccordion accordionDefaultOpen={false} />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
