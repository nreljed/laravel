import * as React from "react";
import { Head, usePage, router } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { SettingsNav } from "@/Components/settings/SettingsNav";
import { Tabs, TabsContent } from "@/Components/ui/tabs";
import { GeneralSection } from "@/Components/settings/sections/GeneralSection";
import { RegistrationSection } from "@/Components/settings/sections/RegistrationSection";
import { NotificationSection } from "@/Components/settings/sections/NotificationSection";
import { SecuritySection } from "@/Components/settings/sections/SecuritySection";
import { EmailSection } from "@/Components/settings/sections/EmailSection";
import { TemplateSimulationSection } from "@/Components/settings/sections/TemplateSimulationSection";
import { MaintenanceSection } from "@/Components/settings/sections/MaintenanceSection";
import { LocalizationSection } from "@/Components/settings/sections/LocalizationSection";
import { FormattingSection } from "@/Components/settings/sections/FormattingSection";
import { DateFormatSection } from "@/Components/settings/sections/DateFormatSection";
import { toast } from "sonner";

interface AdminSettingsProps {
  settings?: Record<string, Record<string, string>>;
  roles?: { id: number; name: string; slug: string }[];
}

export default function AdminSettings({
  settings = {},
  roles = [],
}: AdminSettingsProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;

  const generalData = settings.general || {};
  const formattingData = settings.formatting || {};
  const flatSettings = {
    ...generalData,
    ...formattingData,
    ...settings.registration,
    ...settings.notifications,
    ...settings.security,
    ...settings.email,
    ...settings.maintenance,
    ...settings.localization,
  };

  const [genData, setGenData] = React.useState(generalData);
  const [fmtData, setFmtData] = React.useState(formattingData);
  const [genErrors, setGenErrors] = React.useState({});
  const [fmtErrors, setFmtErrors] = React.useState({});
  const [genLoading, setGenLoading] = React.useState(false);
  const [fmtLoading, setFmtLoading] = React.useState(false);

  if (flash?.status) toast.success(flash.status);

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
        const pg = page as { props?: { settings?: Record<string, Record<string, string>> } };
        if (pg.props?.settings?.general) setGenData(pg.props.settings.general);
        toast.success("General settings saved.");
        setGenLoading(false);
      },
      onError: (errs) => {
        setGenErrors(errs);
        toast.error("Failed to save.");
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
        const pg = page as { props?: { settings?: Record<string, Record<string, string>> } };
        if (pg.props?.settings?.formatting) setFmtData(pg.props.settings.formatting);
        toast.success("Formatting settings saved.");
        setFmtLoading(false);
      },
      onError: (errs) => {
        setFmtErrors(errs);
        toast.error("Failed to save.");
        setFmtLoading(false);
      },
    });
  };

  const [activeTab, setActiveTab] = React.useState(() => {
    if (typeof window !== "undefined") {
      const params = new URLSearchParams(location.search);
      return params.get("tab") || "general";
    }
    return "general";
  });

  const handleTabChange = (value: string) => {
    setActiveTab(value);
    const searchParams = new URLSearchParams(location.search);
    searchParams.set("tab", value);
    const qs = searchParams.toString();
    router.visit(location.pathname + (qs ? "?" + qs : ""), {
      replace: true,
      preserveState: true,
    });
  };

  return (
    <AdminLayout title="Settings">
      <Head title="Settings" />
      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Settings</h2>
          <p className="text-muted-foreground">Manage your application configuration.</p>
        </div>

        <SettingsNav
          activeTab={activeTab}
          onTabChange={handleTabChange}
        />

        <Tabs value={activeTab} onValueChange={handleTabChange} className="space-y-4">
          <TabsContent value="general" className="mt-6 space-y-6">
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
            <DateFormatSection asAccordion />
          </TabsContent>

          <TabsContent value="registration" className="mt-6">
            <RegistrationSection settings={flatSettings} roles={roles} asAccordion />
          </TabsContent>

          <TabsContent value="notifications" className="mt-6">
            <NotificationSection settings={flatSettings} asAccordion />
          </TabsContent>

          <TabsContent value="security" className="mt-6">
            <SecuritySection settings={flatSettings} asAccordion />
          </TabsContent>

          <TabsContent value="email" className="mt-6 space-y-6">
            <EmailSection asAccordion />
            <TemplateSimulationSection asAccordion />
          </TabsContent>

          <TabsContent value="maintenance" className="mt-6">
            <MaintenanceSection settings={flatSettings} asAccordion />
          </TabsContent>

          <TabsContent value="localization" className="mt-6">
            <LocalizationSection settings={flatSettings} asAccordion />
          </TabsContent>
        </Tabs>
      </div>
    </AdminLayout>
  );
}
