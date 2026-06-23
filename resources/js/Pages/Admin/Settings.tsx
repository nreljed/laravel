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
import { ModuleSection } from "@/Components/settings/sections/ModuleSection";
import { FormattingSection } from "@/Components/settings/sections/FormattingSection";
import { DateFormatSection } from "@/Components/settings/sections/DateFormatSection";
import { PropertiesSection } from "@/Components/settings/sections/PropertiesSection";
import { BookingSection } from "@/Components/settings/sections/BookingSection";
import { ECommerceSection } from "@/Components/settings/sections/ECommerceSection";
import { toast } from "sonner";

interface AdminSettingsProps {
  settings?: Record<string, Record<string, string>>;
  availableModules?: { id: number; name: string; slug: string; enabled: boolean; version: string }[];
  modules?: { enabled: string[] };
  roles?: { id: number; name: string; slug: string }[];
}

export default function AdminSettings({ settings = {}, availableModules = [], modules, roles = [] }: AdminSettingsProps) {
  const flash = usePage().props.flash as Record<string, string> | undefined;
  const enabledModules = modules?.enabled ?? [];

  const generalData = settings.general || {};
  const formattingData = settings.formatting || {};
  const propertiesData = settings.properties || {};
  const bookingData = settings.booking || {};

  const flatSettings = {
    ...generalData,
    ...formattingData,
    ...(settings.registration || {}),
    ...(settings.notifications || {}),
    ...(settings.security || {}),
    ...(settings.email || {}),
    ...(settings.maintenance || {}),
    ...(settings.localization || {}),
  };

  const [genData, setGenData] = React.useState(generalData);
  const [fmtData, setFmtData] = React.useState(formattingData);
  const [propData, setPropData] = React.useState(propertiesData);
  const [bookData, setBookData] = React.useState(bookingData);
  const [genErrors, setGenErrors] = React.useState({});
  const [fmtErrors, setFmtErrors] = React.useState({});
  const [propErrors, setPropErrors] = React.useState({});
  const [bookErrors, setBookErrors] = React.useState({});
  const [genLoading, setGenLoading] = React.useState(false);
  const [fmtLoading, setFmtLoading] = React.useState(false);
  const [propLoading, setPropLoading] = React.useState(false);
  const [bookLoading, setBookLoading] = React.useState(false);

  React.useEffect(() => { setGenData(settings.general || {}); }, [settings.general]);
  React.useEffect(() => { setFmtData(settings.formatting || {}); }, [settings.formatting]);
  React.useEffect(() => { setPropData(settings.properties || {}); }, [settings.properties]);
  React.useEffect(() => { setBookData(settings.booking || {}); }, [settings.booking]);

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
      onSuccess: () => { toast.success("General settings saved."); setGenLoading(false); },
      onError: (errs) => { setGenErrors(errs); toast.error("Failed to save."); setGenLoading(false); },
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
      onSuccess: () => { toast.success("Formatting settings saved."); setFmtLoading(false); },
      onError: (errs) => { setFmtErrors(errs); toast.error("Failed to save."); setFmtLoading(false); },
    });
  };

  const handlePropSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (propLoading) return;
    setPropLoading(true);
    setPropErrors({});
    router.put(route("dashboard.admin.settings.update"), propData, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: () => { toast.success("Property settings saved."); setPropLoading(false); },
      onError: (errs) => { setPropErrors(errs); toast.error("Failed to save."); setPropLoading(false); },
    });
  };

  const handleBookSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (bookLoading) return;
    setBookLoading(true);
    setBookErrors({});
    router.put(route("dashboard.admin.settings.update"), bookData, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: () => { toast.success("Booking settings saved."); setBookLoading(false); },
      onError: (errs) => { setBookErrors(errs); toast.error("Failed to save."); setBookLoading(false); },
    });
  };

  const [activeTab, setActiveTab] = React.useState(() => {
    if (typeof window !== "undefined") {
      const params = new URLSearchParams(window.location.search);
      return params.get("tab") || "general";
    }
    return "general";
  });

  const handleTabChange = (value: string) => {
    setActiveTab(value);
    const searchParams = new URLSearchParams(window.location.search);
    searchParams.set("tab", value);
    const qs = searchParams.toString();
    router.visit(window.location.pathname + (qs ? "?" + qs : ""), { replace: true, preserveState: true });
  };

  return (
    <AdminLayout title="Settings">
      <Head title="Settings" />
      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Settings</h2>
          <p className="text-muted-foreground">Manage your application configuration.</p>
        </div>

        <SettingsNav activeTab={activeTab} onTabChange={handleTabChange} enabledModules={enabledModules} />

        <Tabs value={activeTab} onValueChange={handleTabChange} className="space-y-4">
          <TabsContent value="general" className="mt-6 space-y-6">
            <GeneralSection data={genData} onChange={(f, v) => setGenData((p) => ({ ...p, [f]: v }))} errors={genErrors} loading={genLoading} onSubmit={handleGenSubmit} asAccordion />
            <FormattingSection data={fmtData} onChange={(f, v) => setFmtData((p) => ({ ...p, [f]: v }))} errors={fmtErrors} loading={fmtLoading} onSubmit={handleFmtSubmit} />
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

          <TabsContent value="modules" className="mt-6">
            <ModuleSection availableModules={availableModules} asAccordion />
          </TabsContent>

          {enabledModules.includes("properties") && (
            <TabsContent value="properties" className="mt-6">
              <PropertiesSection
                data={propData}
                onChange={(f, v) => setPropData((p) => ({ ...p, [f]: v }))}
                errors={propErrors}
                loading={propLoading}
                onSubmit={handlePropSubmit}
                asAccordion
              />
            </TabsContent>
          )}

          {enabledModules.includes("bookings") && (
            <TabsContent value="booking" className="mt-6">
              <BookingSection
                data={bookData}
                onChange={(f, v) => setBookData((p) => ({ ...p, [f]: v }))}
                errors={bookErrors}
                loading={bookLoading}
                onSubmit={handleBookSubmit}
                asAccordion
              />
            </TabsContent>
          )}

          {enabledModules.includes("ecommerce") && (
            <TabsContent value="ecommerce" className="mt-6">
              <ECommerceSection asAccordion />
            </TabsContent>
          )}
        </Tabs>
      </div>
    </AdminLayout>
  );
}
