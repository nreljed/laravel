import { Repeater } from "@nreljed/platform/Components/shared/repeater";
import { useRepeaterConfig } from "@nreljed/platform/hooks/use-repeater-config";
import { Building2 } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function UnitsSection({ data, onChange, loading }: BookingSectionProps) {
  const { getFields, getLimits } = (useRepeaterConfig as any)("booking");

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <Building2 className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Bookable Units</h2>
          <p className="text-sm text-muted-foreground">
            Set up rooms, suites, or dorms within this property for per-unit booking. When units are
            defined, guests must select a specific unit â€” the entire property is no longer bookable
            as one. Leave empty for standard whole-property booking.
          </p>
        </div>
      </div>

      <Repeater
        label="Bookable Units"
        description="Add units to enable room/hotel-style booking"
        value={data.unit_config || []}
        onChange={(val: any) => onChange("unit_config", val)}
        fields={Object.values(getFields("unit_config"))}
        maxItems={getLimits("unit_config").max}
        minItems={getLimits("unit_config").min}
        accordionMode={true}
        titleField="name"
        accordionDefaultOpen={true}
        disabled={loading}
      />
    </div>
  );
}

export default UnitsSection;
