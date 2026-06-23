import { useCallback, useMemo, useState } from "react"
import { Section, SectionField } from "@/Components/section"
import { Input } from "@/Components/ui/input"
import { Combobox, ComboboxTrigger, ComboboxValue, ComboboxContent, ComboboxInput, ComboboxEmpty, ComboboxGroup, ComboboxItem, ComboboxLabel, ComboboxList, ComboboxSeparator } from "@/Components/ui/combobox"
import { Switch } from "@/Components/ui/switch"
import { MapPinIcon } from "lucide-react"

interface CityTerm {
  id: number
  name: string
  slug: string
  parent_id?: number
  parent_name?: string
}

interface AddressSectionOptions {
  cities?: CityTerm[]
}

interface AddressSectionData {
  meta?: {
    enable_address?: boolean
    address?: string
    locality?: string
    zip?: string
    google_map?: string
  }
  taxonomies?: {
    property_city?: string
  }
}

interface AddressSectionProps {
  data: AddressSectionData
  onChange: (field: string, value: unknown) => void
  errors: Record<string, string>
  loading?: boolean
  onSubmit?: (e: React.FormEvent) => void
  options?: AddressSectionOptions
}

export function AddressSection({ data, onChange, errors, loading = false, onSubmit, options = {} }: AddressSectionProps) {
  const { cities = [] } = options
  const enableAddress = data.meta?.enable_address ?? false
  const hasLocationData = !!(data.meta?.address || data.meta?.locality || data.meta?.zip)
  const [citySearch, setCitySearch] = useState("")

  const handleMetaChange = useCallback((field: string, value: unknown) => {
    onChange(`meta.${field}`, value)
  }, [onChange])

  const handleTaxonomyChange = useCallback((taxonomy: string, value: string) => {
    onChange(`taxonomies.${taxonomy}`, value)
  }, [onChange])

  const groupedCities = useMemo(() => {
    const hasParentInfo = cities.some((c) => typeof c.parent_id === "number" && c.parent_id > 0)
    if (!hasParentInfo) return null
    const governorates = cities.filter((c) => !c.parent_id || c.parent_id === 0)
    const cityItems = cities.filter((c) => c.parent_id && c.parent_id > 0)
    const groups = governorates.map((gov) => ({
      governorate: gov,
      cities: cityItems.filter((c) => c.parent_id === gov.id),
    })).filter((g) => g.cities.length > 0)
    if (!citySearch) return groups
    const s = citySearch.toLowerCase()
    return groups.map((g) => ({ ...g, cities: g.cities.filter((c) => c.name.toLowerCase().includes(s)) })).filter((g) => g.cities.length > 0)
  }, [cities, citySearch])

  const flatFilteredCities = useMemo(() => {
    if (!citySearch) return cities
    return cities.filter((c) => c.name.toLowerCase().includes(citySearch.toLowerCase()))
  }, [cities, citySearch])

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

      <SectionField label="City" htmlFor="property_city" error={errors?.["taxonomies.property_city"]}>
        <Combobox value={data.taxonomies?.property_city || undefined} onValueChange={(value: string) => { handleTaxonomyChange("property_city", value); setCitySearch("") }} disabled={loading}>
          <ComboboxTrigger id="property_city" className="w-full font-normal">
            <ComboboxValue placeholder="Select city">
              {(value: string | null) => {
                if (!value) return null
                const city = cities.find((c) => String(c.id) === value)
                return city?.name || value
              }}
            </ComboboxValue>
          </ComboboxTrigger>
          <ComboboxContent>
            <ComboboxInput placeholder="Search city..." value={citySearch} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setCitySearch(e.target.value)} />
            <ComboboxEmpty>No city found.</ComboboxEmpty>
            <ComboboxList>
              {groupedCities
                ? groupedCities.map((group, i) => (
                    <>
                      {i > 0 && <ComboboxSeparator />}
                      <ComboboxGroup>
                        <ComboboxLabel>{group.governorate.name}</ComboboxLabel>
                        {group.cities.map((city) => (
                          <ComboboxItem key={city.id} value={String(city.id)}>{city.name}</ComboboxItem>
                        ))}
                      </ComboboxGroup>
                    </>
                  ))
                : flatFilteredCities.map((city) => (
                    <ComboboxItem key={city.id} value={String(city.id)}>{city.name}</ComboboxItem>
                  ))}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      </SectionField>

      <SectionField label="Google Maps Link" htmlFor="google_map" error={errors?.google_map} description="Paste the share link from Google Maps">
        <Input id="google_map" type="url" value={data.meta?.google_map || ""} onChange={(e) => handleMetaChange("google_map", e.target.value)} placeholder="Paste Google Maps share link" disabled={loading} />
      </SectionField>
    </Section>
  )
}

export default AddressSection
