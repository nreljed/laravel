import { useCallback, useMemo } from "react"
import { Section, SectionField } from "@/Components/section"
import { Repeater } from "@/Components/ui/repeater"
import { Switch } from "@/Components/ui/switch"
import { Layers } from "lucide-react"

interface DetailsSectionData {
  meta?: {
    enable_details?: boolean
    enable_floors?: boolean
    additional_details?: unknown
    floor_plans?: unknown
  }
}

interface DetailsSectionProps {
  data: DetailsSectionData
  onChange: (field: string, value: unknown) => void
  errors: Record<string, string>
  loading: boolean
  onSubmit?: (e: React.FormEvent) => void
}

const additionalDetailFields = [
  { name: "detail_title", label: "Title", type: "text" as const, placeholder: "e.g. Pet Policy" },
  { name: "detail_value", label: "Value", type: "text" as const, placeholder: "e.g. Pets allowed on request" },
]

const floorPlanFields = [
  { name: "floor_name", label: "Floor Name", type: "text" as const, placeholder: "e.g. Ground Floor", required: true },
  { name: "floor_price", label: "Price", type: "number" as const, placeholder: "0" },
  { name: "floor_surface", label: "Surface (m²)", type: "number" as const, placeholder: "0" },
  { name: "floor_bedrooms", label: "Bedrooms", type: "number" as const, placeholder: "0" },
  { name: "floor_bathrooms", label: "Bathrooms", type: "number" as const, placeholder: "0" },
  { name: "floor_desc", label: "Description", type: "textarea" as const, placeholder: "Floor description...", fullWidth: true },
]

export function DetailsSection({ data, onChange, errors, loading, onSubmit }: DetailsSectionProps) {
  const handleMetaChange = useCallback((field: string, value: unknown) => {
    onChange(`meta.${field}`, value)
  }, [onChange])

  const enableDetails = data.meta?.enable_details ?? false
  const enableFloors = data.meta?.enable_floors ?? false

  const additionalDetailsValue = useMemo(() => {
    const v = data.meta?.additional_details
    if (!v) return []
    if (Array.isArray(v)) return v as Array<Record<string, unknown>>
    try { return JSON.parse(v as string) as Array<Record<string, unknown>> } catch { return [] }
  }, [data.meta?.additional_details])

  const floorPlansValue = useMemo(() => {
    const v = data.meta?.floor_plans
    if (!v) return []
    if (Array.isArray(v)) return v as Array<Record<string, unknown>>
    try { return JSON.parse(v as string) as Array<Record<string, unknown>> } catch { return [] }
  }, [data.meta?.floor_plans])

  return (
    <>
      <Section title="Additional Details" description="Add custom details about the property" icon={Layers} onSubmit={onSubmit} loading={loading} error={errors?.general}>
        <SectionField className="md:col-span-2">
          <div className="flex flex-row items-center justify-between rounded-lg border p-4">
            <div className="space-y-0.5">
              <label className="text-base font-medium">Enable Additional Details</label>
              <p className="text-sm text-muted-foreground">Add custom amenity titles and values</p>
            </div>
            <Switch checked={enableDetails} onCheckedChange={(checked) => handleMetaChange("enable_details", checked)} disabled={loading} />
          </div>
        </SectionField>
        {enableDetails && (
          <SectionField className="md:col-span-2">
            <Repeater
              label="Details"
              description="Add custom title/value pairs"
              value={additionalDetailsValue}
              onChange={(value) => handleMetaChange("additional_details", value)}
              fields={additionalDetailFields}
              maxItems={20}
              disabled={loading}
            />
          </SectionField>
        )}
      </Section>

      <Section title="Floor Plans" description="Add details for multiple floors or rooms" icon={Layers} onSubmit={onSubmit} loading={loading} error={errors?.general}>
        <SectionField className="md:col-span-2">
          <div className="flex flex-row items-center justify-between rounded-lg border p-4">
            <div className="space-y-0.5">
              <label className="text-base font-medium">Enable Floor Plans</label>
              <p className="text-sm text-muted-foreground">Add details for multiple floors or rooms</p>
            </div>
            <Switch checked={enableFloors} onCheckedChange={(checked) => handleMetaChange("enable_floors", checked)} disabled={loading} />
          </div>
        </SectionField>
        {enableFloors && (
          <SectionField className="md:col-span-2">
            <Repeater
              label="Floor Plans"
              description="Add floor or room details"
              value={floorPlansValue}
              onChange={(value) => handleMetaChange("floor_plans", value)}
              fields={floorPlanFields}
              maxItems={10}
              disabled={loading}
            />
          </SectionField>
        )}
      </Section>
    </>
  )
}

export default DetailsSection
