import { lazy, Suspense } from "react"
import { Section } from "@/Components/section"

const MapSectionInner = lazy(() => import("./MapSectionInner"))

interface MapSectionData {
  meta?: {
    latitude?: string
    longitude?: string
  }
}

interface MapSectionProps {
  data: MapSectionData
  onChange: (field: string, value: unknown) => void
  errors?: Record<string, unknown>
  loading?: boolean
  onSubmit?: (e: React.FormEvent) => void
  options?: Record<string, unknown>
}

export function MapSection(props: MapSectionProps) {
  return (
    <Suspense fallback={<MapFallback />}>
      <MapSectionInner {...props} />
    </Suspense>
  )
}

function MapFallback() {
  return (
    <Section title="Location" description="Set property location on the map" icon={() => null} submitLabel="Save Location">
      <div className="relative h-[400px] w-full flex items-center justify-center rounded-lg border overflow-hidden bg-slate-50 dark:bg-slate-900">
        <div className="flex flex-col items-center gap-2 text-muted-foreground">
          <div className="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
          <span className="text-sm">Loading map...</span>
        </div>
      </div>
    </Section>
  )
}

export default MapSection
