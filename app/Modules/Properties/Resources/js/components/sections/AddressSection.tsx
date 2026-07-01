import { useCallback } from "react"
import { Section, SectionField } from "@/Components/section"
import { Input } from "@/Components/ui/input"
import { Switch } from "@/Components/ui/switch"
import { MapPinIcon } from "lucide-react"

interface AddressSectionData {
  meta?: {
    enable_address?: boolean
    address?: string
    locality?: string
    zip?: string
    google_map?: string
  }
}

interface AddressSectionProps {
  data: AddressSectionData
  onChange: (field: string, value: unknown) => void
  errors: Record<string, string>
  loading?: boolean
  onSubmit?: (e: React.FormEvent) => void
}

export function AddressSection({ data, onChange, errors, loading = false, onSubmit }: AddressSectionProps) {
  const enableAddress = data.meta?.enable_address ?? false
  const hasLocationData = !!(data.meta?.address || data.meta?.locality || data.meta?.zip)

  const handleMetaChange = useCallback((field: string, value: unknown) => {
    onChange(`meta.${field}`, value)
  }, [onChange])

  return (
    <Section title="Address Details" description="Property address information" icon={() => null} onSubmit={onSubmit} loading={loading} error={errors?.general}>
      <div className="rounded-lg border p-4">
        <div className="flex flex-row items-center justify-between">
          <div className="space-y-0.5">
            <label className="text-base font-medium">Show Address</label>
            <p className="text-sm text-muted-foreground">Enable to display address details publicly</p>
          </div>
          <Switch checked={enableAddress} onCheckedChange={(checked) => handleMetaChange("enable_address", checked)} disabled={loading} />
        </div>
      </div>

      {enableAddress && (
        <>
          <SectionField label="Street Address" htmlFor="address" error={errors?.address}>
            <Input id="address" value={data.meta?.address || ""} onChange={(e) => handleMetaChange("address", e.target.value)} placeholder="Enter street address" disabled={loading} />
          </SectionField>
          <SectionField label="City / Locality" htmlFor="locality" error={errors?.locality}>
            <Input id="locality" value={data.meta?.locality || ""} onChange={(e) => handleMetaChange("locality", e.target.value)} placeholder="Enter city or town" disabled={loading} />
          </SectionField>
          <SectionField label="Postal Code / Zip" htmlFor="zip" error={errors?.zip}>
            <Input id="zip" value={data.meta?.zip || ""} onChange={(e) => handleMetaChange("zip", e.target.value)} placeholder="Enter postal code" disabled={loading} />
          </SectionField>
        </>
      )}

      {hasLocationData && !enableAddress && (
        <div className="flex items-center gap-2 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
          <MapPinIcon className="size-4 shrink-0" />
          <span>Location data detected{data.meta?.address ? ` — ${data.meta.address}` : ""}{data.meta?.locality ? `, ${data.meta.locality}` : ""} (enable "Show Address" to display)</span>
        </div>
      )}

      <SectionField label="Google Maps Link" htmlFor="google_map" error={errors?.google_map} description="Paste the share link from Google Maps">
        <Input id="google_map" type="url" value={data.meta?.google_map || ""} onChange={(e) => handleMetaChange("google_map", e.target.value)} placeholder="Paste Google Maps share link" disabled={loading} />
      </SectionField>
    </Section>
  )
}

export default AddressSection
