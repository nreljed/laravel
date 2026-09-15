import { useCallback, useMemo, useState } from "react"
import { Section, SectionField } from "@nreljed/platform/Components/shared/section"
import { Input } from "@nreljed/platform/Components/ui/input"
import { Textarea } from "@nreljed/platform/Components/ui/textarea"
import { NumberInput } from "@nreljed/platform/Components/shared/number-input"
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from "@nreljed/platform/Components/ui/select"
import { Combobox, ComboboxTrigger, ComboboxValue, ComboboxContent, ComboboxInput, ComboboxEmpty, ComboboxGroup, ComboboxItem, ComboboxLabel, ComboboxList, ComboboxSeparator } from "@nreljed/platform/Components/ui/combobox"
import { Home } from "lucide-react"

interface CityTerm {
  id: number
  name: string
  slug: string
  governorate: string
  governorate_slug: string
}

interface BasicInfoOptions {
  types?: Array<{ id: number; name: string }>
  statuses?: Array<{ id: number; name: string }>
  cities?: CityTerm[]
  isForRent?: boolean
  bookingEnabled?: boolean
}

interface BasicInfoSectionData {
  title?: string
  code?: string
  status?: string
  meta?: {
    description?: string
    price?: string
    rental_period?: string
    surface?: string
    bedrooms?: number
    bathrooms?: number
    garages?: number
    rooms?: number | string
    year?: string
    floor_level?: number | string
  }
  taxonomies?: {
    property_type?: string
    property_status?: string
    property_city?: string
  }
}

interface BasicInfoSectionProps {
  data: BasicInfoSectionData
  onChange: (field: string, value: unknown) => void
  errors: Record<string, string>
  loading: boolean
  onSubmit?: (e: React.FormEvent) => void
  options?: BasicInfoOptions
}

export function BasicInfoSection({ data, onChange, errors, loading, onSubmit, options = {} }: BasicInfoSectionProps) {
  const { types = [], statuses = [], cities = [], isForRent = false, bookingEnabled = false } = options
  const [citySearch, setCitySearch] = useState("")

  const handleMetaChange = useCallback((field: string, value: unknown) => {
    onChange(`meta.${field}`, value)
  }, [onChange])

  const handleTaxonomyChange = useCallback((taxonomy: string, value: string) => {
    onChange(`taxonomies.${taxonomy}`, value)
  }, [onChange])

  const groupedCities = useMemo(() => {
    const govMap = new Map<string, CityTerm[]>()
    for (const city of cities) {
      const gov = city.governorate
      if (!govMap.has(gov)) govMap.set(gov, [])
      govMap.get(gov)!.push(city)
    }
    const groups = Array.from(govMap.entries()).map(([governorate, cities]) => ({ governorate, cities }))
    if (!citySearch) return groups
    const s = citySearch.toLowerCase()
    return groups.map((g) => ({ ...g, cities: g.cities.filter((c) => c.name.toLowerCase().includes(s)) })).filter((g) => g.cities.length > 0)
  }, [cities, citySearch])

  return (
    <Section
      title="Basic Information"
      description="Property details, price, and specifications"
      icon={Home}
      onSubmit={onSubmit}
      loading={loading}
      error={errors?.general || errors?.title}
      submitLabel="Save Details"
      columns={2}
    >
      <SectionField label="Property Title" htmlFor="title" error={errors?.title} required className="md:col-span-2">
        <Input id="title" value={data.title || ""} onChange={(e) => onChange("title", e.target.value)} placeholder="Enter property title" disabled={loading} required />
      </SectionField>

      <SectionField label="Property Code" htmlFor="code" error={errors?.code} className="md:col-span-2">
        <Input id="code" value={data.code || ""} onChange={(e) => onChange("code", e.target.value)} placeholder="Auto-generated if left blank (e.g. PR-0001)" maxLength={20} disabled={loading} className="font-mono uppercase" />
      </SectionField>

      <SectionField label="Description" htmlFor="description" error={errors?.description} required className="md:col-span-2">
        <Textarea id="description" value={data.meta?.description || ""} onChange={(e) => handleMetaChange("description", e.target.value)} placeholder="Enter property description" rows={4} disabled={loading} required />
      </SectionField>

      <SectionField label="Property Type" htmlFor="property_type" error={errors?.["taxonomies.property_type"]} required>
        <Combobox
          items={types.map((t) => ({ value: String(t.id), label: t.name }))}
          value={data.taxonomies?.property_type || undefined}
          onValueChange={(value: string | null) => handleTaxonomyChange("property_type", value ?? "")}
          disabled={loading}
        >
          <ComboboxTrigger id="property_type" className="w-full font-normal">
            <ComboboxValue placeholder="Select your property type" />
          </ComboboxTrigger>
          <ComboboxContent>
            <ComboboxInput placeholder="Search property type..." />
            <ComboboxEmpty>No property type found.</ComboboxEmpty>
            <ComboboxList>
              {(item: { value: string; label: string }) => (
                <ComboboxItem key={item.value} value={item.value}>{item.label}</ComboboxItem>
              )}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      </SectionField>

      <SectionField label="Property Status" htmlFor="property_status" error={errors?.["taxonomies.property_status"]} required>
        {bookingEnabled ? (
          <div className="text-sm text-muted-foreground border rounded-md px-3 py-2 bg-muted/50">For Rent (managed by Booking)</div>
        ) : (
          <Combobox
            items={statuses.map((s) => ({ value: String(s.id), label: s.name }))}
            value={data.taxonomies?.property_status || undefined}
            onValueChange={(value: string | null) => handleTaxonomyChange("property_status", value ?? "")}
            disabled={loading}
          >
            <ComboboxTrigger id="property_status" className="w-full font-normal">
              <ComboboxValue placeholder="Select your status" />
            </ComboboxTrigger>
            <ComboboxContent>
              <ComboboxInput placeholder="Search status..." />
              <ComboboxEmpty>No status found.</ComboboxEmpty>
              <ComboboxList>
                {(item: { value: string; label: string }) => (
                  <ComboboxItem key={item.value} value={item.value}>{item.label}</ComboboxItem>
                )}
              </ComboboxList>
            </ComboboxContent>
          </Combobox>
        )}
      </SectionField>

      <SectionField label="Property City" htmlFor="property_city" error={errors?.["taxonomies.property_city"]} required>
        <Combobox items={cities.map((c) => ({ value: String(c.id), label: c.name }))} value={data.taxonomies?.property_city || undefined} onValueChange={(value: string | null) => { handleTaxonomyChange("property_city", value ?? ""); setCitySearch("") }} disabled={loading}>
          <ComboboxTrigger id="property_city" className="w-full font-normal">
            <ComboboxValue placeholder="Select your city" />
          </ComboboxTrigger>
          <ComboboxContent>
            <ComboboxInput placeholder="Search city..." value={citySearch} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setCitySearch(e.target.value)} />
            <ComboboxEmpty>No city found.</ComboboxEmpty>
            <ComboboxList>
              {groupedCities.map((group, i) => (
                <>
                  {i > 0 && <ComboboxSeparator />}
                  <ComboboxGroup>
                    <ComboboxLabel>{group.governorate}</ComboboxLabel>
                    {group.cities.map((city) => (
                      <ComboboxItem key={city.id} value={String(city.id)}>{city.name}</ComboboxItem>
                    ))}
                  </ComboboxGroup>
                </>
              ))}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      </SectionField>

      <SectionField label="Price" htmlFor="price" error={errors?.price}>
        <Input id="price" type="number" value={data.meta?.price || ""} onChange={(e) => handleMetaChange("price", e.target.value)} placeholder={bookingEnabled ? "Managed by Booking" : "Enter price"} disabled={loading || bookingEnabled} />
      </SectionField>

      {isForRent && !bookingEnabled && (
        <SectionField label="Rental Period" htmlFor="rental_period" error={errors?.rental_period}>
          <Select value={data.meta?.rental_period || "month"} onValueChange={(value) => handleMetaChange("rental_period", value)} disabled={loading}>
            <SelectTrigger id="rental_period"><SelectValue placeholder="Select period" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="day">Per Day</SelectItem>
              <SelectItem value="week">Per Week</SelectItem>
              <SelectItem value="month">Per Month</SelectItem>
              <SelectItem value="year">Per Year</SelectItem>
            </SelectContent>
          </Select>
        </SectionField>
      )}
      {isForRent && bookingEnabled && (
        <SectionField label="Rental Period" htmlFor="rental_period">
          <div className="text-sm text-muted-foreground border rounded-md px-3 py-2 bg-muted/50">Managed by Booking (per-night pricing)</div>
        </SectionField>
      )}

      <SectionField label="Surface Area (m²)" htmlFor="surface" error={errors?.surface}>
        <Input id="surface" type="number" value={data.meta?.surface || ""} onChange={(e) => handleMetaChange("surface", e.target.value)} placeholder="Enter surface area" disabled={loading} />
      </SectionField>

      <SectionField label="Year Built" htmlFor="year" error={errors?.year}>
        <Input id="year" type="number" value={data.meta?.year || ""} onChange={(e) => handleMetaChange("year", e.target.value)} placeholder="Year" disabled={loading} min={1800} max={new Date().getFullYear()} />
      </SectionField>

      <div className="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
        <SectionField label="Bedrooms" htmlFor="bedrooms" error={errors?.bedrooms}>
          <NumberInput id="bedrooms" value={data.meta?.bedrooms || 0} onValueChange={(value) => handleMetaChange("bedrooms", value)} min={0} max={20} disabled={loading} />
        </SectionField>
        <SectionField label="Bathrooms" htmlFor="bathrooms" error={errors?.bathrooms}>
          <NumberInput id="bathrooms" value={data.meta?.bathrooms || 0} onValueChange={(value) => handleMetaChange("bathrooms", value)} min={0} max={20} disabled={loading} />
        </SectionField>
        <SectionField label="Garages" htmlFor="garages" error={errors?.garages}>
          <NumberInput id="garages" value={data.meta?.garages || 0} onValueChange={(value) => handleMetaChange("garages", value)} min={0} max={10} disabled={loading} />
        </SectionField>
        <SectionField label="Rooms" htmlFor="rooms" error={errors?.rooms}>
          <NumberInput id="rooms" value={Number(data.meta?.rooms || 0)} onValueChange={(value) => handleMetaChange("rooms", value)} min={0} max={50} disabled={loading} />
        </SectionField>
        <SectionField label="Floor Level" htmlFor="floor_level" error={errors?.floor_level}>
          <NumberInput id="floor_level" value={Number(data.meta?.floor_level || 0)} onValueChange={(value) => handleMetaChange("floor_level", value)} min={0} max={100} disabled={loading} />
        </SectionField>
      </div>
    </Section>
  )
}

export default BasicInfoSection
