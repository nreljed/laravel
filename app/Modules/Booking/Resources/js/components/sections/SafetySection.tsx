import { Textarea } from "@/Components/ui/textarea";
import { Checkbox } from "@/Components/ui/checkbox";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Shield, AlertCircle } from "lucide-react";

const SAFETY_FEATURES = [
  { value: "smoke_detector", label: "Smoke Detector" },
  { value: "carbon_monoxide", label: "Carbon Monoxide Detector" },
  { value: "fire_extinguisher", label: "Fire Extinguisher" },
  { value: "first_aid_kit", label: "First Aid Kit" },
  { value: "security_cameras", label: "Security Cameras (must disclose location)" },
  { value: "indoor_locks", label: "Door Locks on Bedrooms" },
  { value: "lockbox", label: "Lockbox / Key Safe" },
  { value: "smart_lock", label: "Smart Lock / Key Card" },
  { value: "wifi", label: "WiFi & Connectivity" },
  { value: "emergency_contact", label: "Emergency Contact Info Provided" },
  { value: "evacuation_guide", label: "Evacuation Route Guide" },
  { value: "pool_fence", label: "Pool Fenced / Childproof" },
  { value: "hot_tub", label: "Hot Tub (safety info provided)" },
] as const;

interface BookingSectionProps {
  data: Record<string, unknown>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function SafetySection({ data, onChange, loading }: BookingSectionProps) {
  const safetyFeatures = (data.safety_features as string[]) || [];
  const hasCameras = safetyFeatures.includes("security_cameras");

  const handleToggleFeature = (feature: string) => {
    const updated = safetyFeatures.includes(feature)
      ? safetyFeatures.filter((f) => f !== feature)
      : [...safetyFeatures, feature];
    onChange("safety_features", updated);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <Shield className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Safety Features</h2>
          <p className="text-sm text-muted-foreground">
            List the safety and security amenities available at your property
          </p>
        </div>
      </div>

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          These features help guests assess the safety of your property. Be honest — incorrect
          claims may lead to liability issues.
        </AlertDescription>
      </Alert>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        {SAFETY_FEATURES.map((feature) => (
          <label
            key={feature.value}
            className="flex items-start gap-3 p-3 rounded-lg border hover:bg-muted transition-colors cursor-pointer"
          >
            <Checkbox
              checked={safetyFeatures.includes(feature.value)}
              onCheckedChange={() => handleToggleFeature(feature.value)}
              disabled={loading}
              className="mt-0.5"
            />
            <span className="text-sm">{feature.label}</span>
          </label>
        ))}
      </div>

      {hasCameras && (
        <div>
          <label className="text-sm font-medium mb-1 block">
            Security Camera Locations &amp; Privacy Policy
          </label>
          <Textarea
            value={(data.security_cameras_details as string) || ""}
            onChange={(e) => onChange("security_cameras_details", e.target.value)}
            placeholder="Be very specific about where cameras are located and confirm they do NOT record private areas (bedrooms, bathrooms)"
            rows={3}
            disabled={loading}
          />
          <p className="text-xs text-muted-foreground mt-1">
            Required when Security Cameras is selected. Guests must be informed of all cameras.
          </p>
        </div>
      )}
    </div>
  );
}

export default SafetySection;
