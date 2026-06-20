import React, { useCallback } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Button } from "@/Components/ui/button";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { DollarSign, Layers, Calendar, Map, Settings, BookOpen } from "lucide-react";
import PricingSection from "../components/sections/PricingSection";
import RulesTabSection from "../components/sections/RulesTabSection";
import AvailabilityModeSection from "../components/sections/AvailabilityModeSection";
import UnitsSection from "../components/sections/UnitsSection";
import NeighborhoodInfoSection from "../components/sections/NeighborhoodInfoSection";

interface BookingSettingsProps {
  property: { id: number; title: string };
  settings?: Record<string, unknown>;
  house_rules?: unknown[];
}

export default function BookingSettings({
  property,
  settings = {},
  house_rules = [],
}: BookingSettingsProps) {
  const {
    data,
    setData,
    put,
    processing,
    errors: _errors,
  } = useForm({
    ...settings,
    house_rules: house_rules as unknown,
  });

  const handleFieldChange = useCallback(
    (field: string, value: unknown) => {
      setData(field as keyof typeof data, value as unknown);
    },
    [setData],
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/dashboard/properties/${property.id}/booking`, {
      onSuccess: () => toast.success("Booking settings saved!"),
      onError: () => toast.error("Failed to save settings"),
    });
  };

  return (
    <AuthenticatedLayout header={`Booking Settings - ${property.title}`}>
      <div className="max-w-4xl space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard/properties">Properties</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Booking Settings — {property.title}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center gap-3">
          <Settings className="h-6 w-6 text-foreground shrink-0" />
          <div>
            <h1 className="text-2xl font-bold text-foreground">Booking Settings</h1>
            <p className="text-muted-foreground">{property.title}</p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <Tabs defaultValue="pricing" className="w-full">
            <TabsList className="!h-auto flex-wrap gap-1.5 p-1.5">
              <TabsTrigger className="shrink-0 gap-2" value="pricing">
                <DollarSign className="h-4 w-4 shrink-0" />
                Pricing
              </TabsTrigger>
              <TabsTrigger className="shrink-0 gap-2" value="rules">
                <BookOpen className="h-4 w-4 shrink-0" />
                Rules
              </TabsTrigger>
              <TabsTrigger className="shrink-0 gap-2" value="availability">
                <Calendar className="h-4 w-4 shrink-0" />
                Availability
              </TabsTrigger>
              <TabsTrigger className="shrink-0 gap-2" value="units">
                <Layers className="h-4 w-4 shrink-0" />
                Units
              </TabsTrigger>
              <TabsTrigger className="shrink-0 gap-2" value="info">
                <Map className="h-4 w-4 shrink-0" />
                Info
              </TabsTrigger>
            </TabsList>

            <TabsContent value="pricing">
              <PricingSection
                data={data as unknown}
                onChange={handleFieldChange}
                loading={processing}
              />
            </TabsContent>
            <TabsContent value="rules">
              <RulesTabSection
                data={data as unknown}
                onChange={handleFieldChange}
                loading={processing}
              />
            </TabsContent>
            <TabsContent value="availability">
              <AvailabilityModeSection
                data={data as unknown}
                onChange={handleFieldChange}
                loading={processing}
              />
            </TabsContent>
            <TabsContent value="units">
              <UnitsSection
                data={data as unknown}
                onChange={handleFieldChange}
                loading={processing}
              />
            </TabsContent>
            <TabsContent value="info">
              <NeighborhoodInfoSection
                data={data as unknown}
                onChange={handleFieldChange}
                loading={processing}
              />
            </TabsContent>
          </Tabs>

          <div className="flex gap-3 pt-6 border-t">
            <Button type="submit" disabled={processing} size="lg">
              {processing ? "Saving..." : "Save All Settings"}
            </Button>
            <Button type="button" variant="outline" onClick={() => history.back()}>
              Cancel
            </Button>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
