import { useCallback, useEffect, useMemo } from "react"
import { Head, useForm, usePage } from "@inertiajs/react"
import type { PageProps } from "@inertiajs/core"
import { toast } from "@nreljed/platform/Components/ui/toast"
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@nreljed/platform/Components/ui/breadcrumb"
import { BasicInfoSection, AddressSection, MapSection, VideoSection, MediaSection, FeaturesSection, DetailsSection, BookingSection } from "../components"
import { PublishSection } from "@nreljed/platform/Components/shared/publish-section"

function extractJson(value: unknown): Array<Record<string, unknown>> {
  if (!value) return []
  if (Array.isArray(value)) return value as Array<Record<string, unknown>>
  try { return JSON.parse(value as string) as Array<Record<string, unknown>> } catch { return [] }
}

interface StatusTerm {
  id: number
  name: string
  slug: string
}

interface PropertyFormData {
  title: string
  code: string
  status: string
  publish_date: string | null
  _wpnonce: string
  meta: {
    listing_id: string
    description: string
    price: string
    rental_period: string
    surface: string
    bedrooms: number
    bathrooms: number
    garages: number
    rooms: string
    year: string
    floor_level: string
    enable_address: boolean
    address: string
    locality: string
    zip: string
    latitude: string
    longitude: string
    google_map: string
    video: string
    gallery: Record<string, string>[]
    enable_details: boolean
    additional_details: Record<string, string>[]
    enable_floors: boolean
    floor_plans: Record<string, string>[]
  }
  taxonomies: {
    property_type: string
    property_city: string
    property_status: string
    property_features: string[]
  }
  booking: { enabled: boolean; settings: Record<string, string> }
}

export default function PropertiesForm() {
  const { props } = usePage<{
    property?: {
      id: number
      title?: string
      status?: string
      publish_date?: string | null
      thumbnail_url?: string
    }
    property_types?: Array<{ id: number; name: string }>
    property_cities?: Array<{ id: number; name: string }>
    property_statuses?: StatusTerm[]
    property_features?: Array<{ id: number; name: string }>
    selected_type?: number
    selected_city?: number
    selected_status?: number
    selected_features?: number[]
    meta?: Record<string, unknown>
    nonce?: string
    settings?: Record<string, string>
    booking?: null | { enabled: boolean; settings: Record<string, unknown> }
    flash?: { status?: string; error?: string }
    default_status?: string
    booking_active?: boolean
  } & PageProps>()

  const isEditing = !!props.property
  const propertyData = props.property || { id: 0, title: "", status: "draft", publish_date: null }
  const meta = props.meta || {}
  const nonce = props.nonce || ""
  const settings = props.settings || {}
  const defaultRentalPeriod = (settings.property_rental_period as string) || "month"
  const defaultStatus = props.default_status || "draft"
  const bookingActive = props.booking_active || false
  const bookingData = props.booking || null

  const propertyTypes = props.property_types || []
  const propertyCities = (props.cities || []) as Array<{ id: number; name: string; slug: string; governorate: string; governorate_slug: string }>
  const propertyStatuses = useMemo(() => props.property_statuses || [], [props.property_statuses])
  const propertyFeatures = props.property_features || []
  const selectedType = props.selected_type
  const selectedCity = props.selected_city
  const selectedStatus = props.selected_status
  const selectedFeatures = props.selected_features || []

  const flash = useMemo(() => props.flash || {} as { status?: string; error?: string }, [props.flash])

  useEffect(() => {
    if (flash.status) toast.add({ title: flash.status, type: "success" })
    if (flash.error) toast.add({ title: flash.error, type: "error" })
  }, [flash.status, flash.error])

  const forRentTermId = useMemo(() => {
    const term = propertyStatuses.find((s: StatusTerm) => s.slug === "for-rent")
    return term ? String(term.id) : null
  }, [propertyStatuses])

  const { data, setData, post, processing, errors } = useForm<PropertyFormData>({
    title: propertyData.title || "",
    code: (propertyData as unknown as Record<string, string>).code || "",
    status: propertyData.status || defaultStatus,
    publish_date: propertyData.publish_date || null,
    _wpnonce: nonce,
    meta: {
      listing_id: (meta.listing_id as string) || "",
      description: (propertyData as unknown as Record<string, string>).description || (meta.description as string) || "",
      price: (propertyData as Record<string, unknown>).price?.toString() || (meta.price as string) || "",
      rental_period: (meta.rental_period as string) || defaultRentalPeriod,
      surface: (propertyData as Record<string, unknown>).surface?.toString() || (meta.surface as string) || "",
      bedrooms: (meta.bedrooms as number) || 0,
      bathrooms: (meta.bathrooms as number) || 0,
      garages: (meta.garages as number) || 0,
      rooms: (meta.rooms as string) || "",
      year: (meta.year as string) || "",
      floor_level: (meta.floor_level as string) || "",
      enable_address: (meta.enable_address as boolean) || false,
      address: (propertyData as unknown as Record<string, string>).address || (meta.address as string) || "",
      locality: (propertyData as unknown as Record<string, string>).city || (meta.locality as string) || "",
      zip: (propertyData as unknown as Record<string, string>).zip || (meta.zip as string) || "",
      latitude: (meta.latitude as string) || (propertyData as Record<string, unknown>).latitude?.toString() || "",
      longitude: (meta.longitude as string) || (propertyData as Record<string, unknown>).longitude?.toString() || "",
      google_map: (meta.google_map as string) || "",
      video: (propertyData as unknown as Record<string, string>).video_url || (meta.video as string) || "",
      gallery: (meta.gallery as Record<string, string>[]) || [],
      enable_details: (meta.enable_details as boolean) || false,
      additional_details: (meta.additional_details as Record<string, string>[]) || extractJson(meta.additional_details_json) || [],
      enable_floors: (meta.enable_floors as boolean) || false,
      floor_plans: (meta.floor_plans as Record<string, string>[]) || extractJson(meta.floor_plans_json) || [],
    },
    taxonomies: {
      property_type: selectedType ? String(selectedType) : "",
      property_city: selectedCity ? String(selectedCity) : "",
      property_status: selectedStatus ? String(selectedStatus) : "",
      property_features: selectedFeatures.map(String),
    },
    booking: (bookingData as { enabled: boolean; settings: Record<string, string> }) || { enabled: false, settings: {} },
  })

  useEffect(() => {
    if (typeof window !== "undefined" && nonce) {
      ;(window as unknown as Record<string, unknown>).crmNonce = nonce
    }
  }, [nonce])

  const handleFieldChange = useCallback((field: string, value: unknown) => {
    if (field.startsWith("meta.")) {
      const metaField = field.replace("meta.", "")
      setData((prev) => ({ ...prev, meta: { ...prev.meta, [metaField]: value } }))
    } else if (field.startsWith("taxonomies.")) {
      const taxField = field.replace("taxonomies.", "")
      if (taxField === "property_features" && Array.isArray(value)) {
        setData((prev) => ({ ...prev, taxonomies: { ...prev.taxonomies, property_features: value as string[] } }))
      } else {
        setData((prev) => ({ ...prev, taxonomies: { ...prev.taxonomies, [taxField]: value } }))
      }
    } else if (field.startsWith("booking.")) {
      const bookingField = field.replace("booking.", "")
      if (bookingField === "enabled") {
        setData((prev) => ({
          ...prev,
          booking: { ...prev.booking, enabled: value as boolean },
          ...(value && forRentTermId
            ? { taxonomies: { ...prev.taxonomies, property_status: forRentTermId } }
            : undefined),
        }))
      } else if (bookingField.startsWith("settings.")) {
        const settingField = bookingField.replace("settings.", "")
        setData((prev) => ({
          ...prev,
          booking: { ...prev.booking, settings: { ...prev.booking.settings, [settingField]: value as string } },
        }))
      } else {
        setData((prev) => ({ ...prev, booking: { ...prev.booking, [bookingField]: value } }))
      }
    } else {
      setData(field as keyof PropertyFormData, value as PropertyFormData[keyof PropertyFormData])
    }
  }, [setData, forRentTermId])

  const handleSubmit = useCallback((e?: React.FormEvent) => {
    e?.preventDefault()
    if (!data.title.trim()) { toast.add({ title: "Property title is required", type: "error" }); return }
    if (!data.meta?.description?.trim()) { toast.add({ title: "Property description is required", type: "error" }); return }
    if (!data.taxonomies?.property_type) { toast.add({ title: "Property type is required", type: "error" }); return }
    if (!data.taxonomies?.property_status) { toast.add({ title: "Property status is required", type: "error" }); return }
    if (!data.taxonomies?.property_city) { toast.add({ title: "Property city is required", type: "error" }); return }

    const options = {
      onError: (errs: Record<string, string>) => {
        const first = Object.values(errs)[0]
        if (first) toast.add({ title: first, type: "error" })
      },
    }

    if (isEditing) {
      post(route("dashboard.properties.update", propertyData.id) + "?_method=PUT", options)
    } else {
      post(route("dashboard.properties.store"), options)
    }
  }, [data, post, isEditing, propertyData.id])

  const isForRent = propertyStatuses.find(
    (s: StatusTerm) => String(s.id) === data.taxonomies.property_status
  )?.slug === "for-rent"

  const bookingEnabled = data.booking?.enabled ?? false

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Property" : "Add Property"}>
      <Head title={isEditing ? "Edit Property" : "Add Property"} />
      <div className="max-w-4xl mx-auto space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.index")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.properties.index")}>Properties</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>{isEditing ? "Edit Property" : "Add Property"}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <form onSubmit={handleSubmit} className="space-y-6">
          <BasicInfoSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
            options={{ types: propertyTypes, statuses: propertyStatuses, cities: propertyCities, isForRent, bookingEnabled }}
          />

          {bookingActive && (
            <BookingSection data={data} onChange={handleFieldChange} loading={processing} />
          )}

          <AddressSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
          />

          <MapSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
          />

          <VideoSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
          />

          <MediaSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
            options={{ propertyId: isEditing ? propertyData.id : null }}
          />

          <FeaturesSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
            options={{ features: propertyFeatures }}
          />

          <DetailsSection
            data={data}
            onChange={handleFieldChange}
            errors={errors}
            loading={processing}
          />

          <PublishSection
            status={data.status}
            publishDate={data.publish_date}
            onStatusChange={(s) => handleFieldChange("status", s)}
            onDateChange={(d) => handleFieldChange("publish_date", d)}
            disabled={processing}
            onSubmit={handleSubmit}
            loading={processing}
            cancelUrl={route("dashboard.properties.index")}
          />
        </form>
      </div>
    </AuthenticatedLayout>
  )
}
