import { useMemo } from "react"
import { Section } from "@/Components/section"
import { Checkbox } from "@/Components/ui/checkbox"
import { Label } from "@/Components/ui/label"
import { CheckSquare } from "lucide-react"

interface FeatureTerm {
  id?: number
  term_id?: number
  featureId?: string
  name?: string
  term_name?: string
  parent_id?: number | string
}

interface FeaturesOptions {
  features?: FeatureTerm[]
}

interface FeaturesSectionData {
  taxonomies?: { property_features?: unknown }
}

interface FeaturesSectionProps {
  data: FeaturesSectionData
  onChange: (field: string, value: unknown) => void
  errors?: Record<string, string>
  loading: boolean
  options?: FeaturesOptions
}

export function FeaturesSection({ data, onChange, errors, loading, options = {} }: FeaturesSectionProps) {
  const { features = [] } = options
  const selectedFeatures = useMemo(() => data.taxonomies?.property_features || [], [data.taxonomies?.property_features])

  const featureList = useMemo(() => {
    return features.map((f) => ({
      id: String(f.id ?? f.term_id ?? f.featureId ?? ""),
      name: f.name || f.term_name || "",
    })).filter((f) => f.id && f.name)
  }, [features])

  const selectedSet = useMemo(() => new Set(selectedFeatures as string[]), [selectedFeatures])

  const toggleFeature = (id: string) => {
    const current = selectedFeatures as string[]
    const updated = selectedSet.has(id) ? current.filter((fid) => fid !== id) : [...current, id]
    onChange("taxonomies.property_features", updated)
  }

  if (features.length === 0) return null

  return (
    <Section title="Amenities & Features" description="Select property features" icon={CheckSquare} error={errors?.general} columns={1}>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {featureList.map((feature) => (
          <Label key={feature.id} className="flex items-center gap-2 cursor-pointer rounded-md border p-3 hover:bg-accent has-data-[state=checked]:border-primary">
            <Checkbox checked={selectedSet.has(feature.id)} onCheckedChange={() => toggleFeature(feature.id)} disabled={loading} />
            <span className="text-sm">{feature.name}</span>
          </Label>
        ))}
      </div>
    </Section>
  )
}

export default FeaturesSection
