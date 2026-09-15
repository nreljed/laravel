import { useMemo } from "react";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Repeater } from "@nreljed/platform/Components/shared/repeater";
import { useRepeaterConfig } from "@nreljed/platform/hooks/use-repeater-config";
import { AlertCircle } from "lucide-react";
import { Alert, AlertDescription } from "@nreljed/platform/Components/ui/alert";
import { toast } from "@nreljed/platform/Components/ui/toast";

const PRESET_RULES = [
  {
    type: "noise",
    title: "Quiet Hours",
    description: "Please keep noise to a minimum between 10 PM and 8 AM.",
  },
  {
    type: "smoking",
    title: "No Smoking",
    description: "Smoking is not permitted anywhere in the property.",
  },
  {
    type: "parties",
    title: "No Parties or Events",
    description: "Parties, events, or large gatherings are not allowed.",
  },
  {
    type: "cleaning",
    title: "Basic Cleaning",
    description: "Please wash dishes, take out trash, and leave reasonably clean.",
  },
];

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function RulesSection({ data, onChange, loading }: BookingSectionProps) {
  const { getRepeater, getFields, getLimits } = (useRepeaterConfig as any)("booking");
  const repeater = getRepeater("house_rules");

  const rules = useMemo(() => {
    const raw = data.house_rules;
    if (Array.isArray(raw)) return raw;
    try {
      return JSON.parse((raw as string) || "[]");
    } catch {
      return [];
    }
  }, [data.house_rules]);

  const handleAddPreset = (preset: (typeof PRESET_RULES)[number]) => {
    if (rules.some((r: Record<string, unknown>) => r.rule_type === preset.type)) {
      toast.add({ title: "This rule type already exists", type: "info" });
      return;
    }
    onChange("house_rules", [
      ...rules,
      {
        rule_title: preset.title,
        rule_description: preset.description,
        rule_type: preset.type,
        rule_enforced: true,
      },
    ]);
    toast.add({ title: `Added "${preset.title}"`, type: "success" });
  };

  return (
    <div className="space-y-6">
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          Mark critical rules as "enforced" if guests should be removed for violation.
        </AlertDescription>
      </Alert>

      <div className="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg space-y-3">
        <p className="text-sm font-medium">Quick Add Presets:</p>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
          {PRESET_RULES.map((preset) => (
            <Button
              key={preset.type}
              type="button"
              variant="outline"
              size="sm"
              onClick={() => handleAddPreset(preset)}
              disabled={loading}
            >
              {preset.title}
            </Button>
          ))}
        </div>
      </div>

      <Repeater
        label={typeof repeater?.label === "string" ? repeater.label : "House Rules"}
        description={
          typeof (repeater as any)?.description === "string"
            ? (repeater as any).description
            : "Set clear expectations for guest behavior"
        }
        value={rules}
        onChange={(val: unknown) => onChange("house_rules", val)}
        fields={Object.values(getFields("house_rules"))}
        maxItems={getLimits("house_rules").max}
        minItems={getLimits("house_rules").min}
        accordionMode={true}
        titleField="rule_title"
        accordionDefaultOpen={true}
        disabled={loading}
      />
    </div>
  );
}

export default RulesSection;
