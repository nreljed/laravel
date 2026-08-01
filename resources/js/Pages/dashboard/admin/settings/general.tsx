import { useState, useEffect } from "react";
import { Head, usePage, router, Link } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { AdminSettingsNav } from "@/Components/settings/AdminSettingsNav";
import { GeneralSection } from "@/Components/settings/sections/GeneralSection";
import { FormattingSection } from "@/Components/settings/sections/FormattingSection";
import { DateFormatSection } from "@/Components/settings/sections/DateFormatSection";
import { toast } from "@/Components/ui/toast";

interface GeneralProps {
  settings?: Record<string, string>;
}

export default function GeneralSettings({ settings = {} }: GeneralProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  const [genData, setGenData] = useState(settings);
  const [fmtData, setFmtData] = useState(settings);
  const [genErrors, setGenErrors] = useState({});
  const [fmtErrors, setFmtErrors] = useState({});
  const [genLoading, setGenLoading] = useState(false);
  const [fmtLoading, setFmtLoading] = useState(false);

  useEffect(() => {
    if (flash?.status) toast.add({ title: flash.status, type: "success" });
  }, [flash?.status]);

  const handleGenSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (genLoading) return;
    setGenLoading(true);
    setGenErrors({});
    router.put(route("dashboard.admin.settings.update"), genData, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: (page: unknown) => {
        const pg = page as { props?: { settings?: Record<string, string> } };
        if (pg.props?.settings) setGenData(pg.props.settings);
        toast.add({ title: "General settings saved.", type: "success" });
        setGenLoading(false);
      },
      onError: (errs) => {
        setGenErrors(errs);
        toast.add({ title: "Failed to save.", type: "error" });
        setGenLoading(false);
      },
    });
  };

  const handleFmtSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (fmtLoading) return;
    setFmtLoading(true);
    setFmtErrors({});
    router.put(route("dashboard.admin.settings.update"), fmtData, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: (page: unknown) => {
        const pg = page as { props?: { settings?: Record<string, string> } };
        if (pg.props?.settings) setFmtData(pg.props.settings);
        toast.add({ title: "Formatting settings saved.", type: "success" });
        setFmtLoading(false);
      },
      onError: (errs) => {
        setFmtErrors(errs);
        toast.add({ title: "Failed to save.", type: "error" });
        setFmtLoading(false);
      },
    });
  };

  return (
    <AdminLayout title="Settings">
      <Head title="General Settings" />
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
              <BreadcrumbPage>General Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="flex flex-col md:flex-row gap-8">
          <AdminSettingsNav />
          <div className="flex-1 min-w-0 space-y-6">
            <GeneralSection
              data={genData}
              onChange={(f, v) => setGenData((p) => ({ ...p, [f]: v }))}
              errors={genErrors}
              loading={genLoading}
              onSubmit={handleGenSubmit}
              asAccordion
            />
            <FormattingSection
              data={fmtData}
              onChange={(f, v) => setFmtData((p) => ({ ...p, [f]: v }))}
              errors={fmtErrors}
              loading={fmtLoading}
              onSubmit={handleFmtSubmit}
            />
            <DateFormatSection settings={settings} asAccordion />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
