import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { NumberInput } from "@/Components/ui/number-input";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { MapPin, Lightbulb } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function NeighborhoodInfoSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <MapPin className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Neighborhood & Practical Info</h2>
          <p className="text-sm text-muted-foreground">
            Provide parking, transit, local tips, check-in instructions, and WiFi details — all
            shown to confirmed guests to help them prepare for their stay
          </p>
        </div>
      </div>

      <Alert className="mb-6">
        <Lightbulb className="h-4 w-4" />
        <AlertDescription>
          The more detailed you are, the more confident guests will be about booking.
        </AlertDescription>
      </Alert>

      <div>
        <label className="text-sm font-medium mb-1 block">Neighborhood Description</label>
        <Textarea
          value={data.neighborhood_description || ""}
          onChange={(e) => onChange("neighborhood_description", e.target.value)}
          placeholder="What is this area like? Safe? Quiet? Lively?"
          rows={3}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">Parking Spaces Included</label>
        <NumberInput
          value={data.parking_spaces ?? 0}
          onValueChange={(v: any) => onChange("parking_spaces", v)}
          min={0}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">Parking Information</label>
        <Textarea
          value={data.parking_info || ""}
          onChange={(e) => onChange("parking_info", e.target.value)}
          placeholder="Where can guests park? Free or paid?"
          rows={2}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">Public Transit Information</label>
        <Textarea
          value={data.transit_info || ""}
          onChange={(e) => onChange("transit_info", e.target.value)}
          placeholder="What transit is nearby?"
          rows={2}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">Local Recommendations</label>
        <Textarea
          value={data.local_recommendations || ""}
          onChange={(e) => onChange("local_recommendations", e.target.value)}
          placeholder="Great coffee shop, best pizza, nearby park, etc."
          rows={4}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">Check-in Instructions</label>
        <Textarea
          value={data.check_in_instructions || ""}
          onChange={(e) => onChange("check_in_instructions", e.target.value)}
          placeholder="Step-by-step instructions for entering the property"
          rows={4}
          disabled={loading}
        />
      </div>

      <div>
        <label className="text-sm font-medium mb-1 block">WiFi Password</label>
        <Input
          value={data.wifi_password || ""}
          onChange={(e) => onChange("wifi_password", e.target.value)}
          placeholder="WiFi password (shown to confirmed guests only)"
          disabled={loading}
        />
      </div>
    </div>
  );
}

export default NeighborhoodInfoSection;
