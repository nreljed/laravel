import { useCallback } from "react"
import { Switch } from "@/Components/ui/switch"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs"
import { CalendarCheck, DollarSign, Layers, Calendar, Map, BookOpen } from "lucide-react"
import { PricingSection } from "@modules-booking/components/sections/PricingSection"
import { RulesTabSection } from "@modules-booking/components/sections/RulesTabSection"
import { AvailabilityModeSection } from "@modules-booking/components/sections/AvailabilityModeSection"
import { UnitsSection } from "@modules-booking/components/sections/UnitsSection"
import { NeighborhoodInfoSection } from "@modules-booking/components/sections/NeighborhoodInfoSection"

interface BookingSectionProps {
  data: { booking?: { enabled: boolean; settings: Record<string, unknown> } }
  onChange: (field: string, value: unknown) => void
  loading: boolean
}

export function BookingSection({ data, onChange, loading }: BookingSectionProps) {
  const d = data as { booking?: { enabled: boolean; settings: Record<string, unknown> } }
  const booking = d.booking || { enabled: false, settings: {} }
  const isEnabled = booking.enabled

  const handleToggle = useCallback((checked: boolean) => {
    onChange("booking.enabled", checked)
  }, [onChange])

  const handleSettingChange = useCallback((field: string, value: unknown) => {
    onChange("booking.settings." + field, value)
  }, [onChange])

  return (
    <Card className="border-primary/20">
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CalendarCheck className="h-5 w-5 text-primary shrink-0" />
            <CardTitle className="text-lg">Booking</CardTitle>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">{isEnabled ? "Enabled" : "Disabled"}</span>
            <Switch checked={isEnabled} onCheckedChange={handleToggle} disabled={loading} />
          </div>
        </div>
        <p className="text-sm text-muted-foreground">
          Enable booking functionality for this property. Configure pricing, rules, availability, and guest policies below.
        </p>
      </CardHeader>

      {isEnabled && (
        <CardContent>
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
              <TabsTrigger className="shrink-0 gap-2" value="area">
                <Map className="h-4 w-4 shrink-0" />
                Area
              </TabsTrigger>
            </TabsList>

            <TabsContent value="pricing">
              <PricingSection data={booking.settings} onChange={handleSettingChange} loading={loading} />
            </TabsContent>

            <TabsContent value="rules">
              <RulesTabSection data={booking.settings} onChange={handleSettingChange} loading={loading} />
            </TabsContent>

            <TabsContent value="availability">
              <AvailabilityModeSection data={booking.settings} onChange={handleSettingChange} loading={loading} />
            </TabsContent>

            <TabsContent value="units">
              <UnitsSection data={booking.settings} onChange={handleSettingChange} loading={loading} />
            </TabsContent>

            <TabsContent value="area">
              <NeighborhoodInfoSection data={booking.settings} onChange={handleSettingChange} loading={loading} />
            </TabsContent>
          </Tabs>
        </CardContent>
      )}
    </Card>
  )
}

export default BookingSection
