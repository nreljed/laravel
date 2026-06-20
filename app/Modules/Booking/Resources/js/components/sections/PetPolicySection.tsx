import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Checkbox } from "@/Components/ui/checkbox";
import { Card, CardHeader, CardTitle } from "@/Components/ui/card";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { PawPrint, AlertCircle } from "lucide-react";

const petOptions = [
  { value: "dogs", label: "Dogs" },
  { value: "cats", label: "Cats" },
  { value: "birds", label: "Birds" },
  { value: "fish", label: "Fish" },
  { value: "rabbits", label: "Rabbits" },
  { value: "other", label: "Other" },
] as const;

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function PetPolicySection({ data, onChange, loading }: BookingSectionProps) {
  const petsAllowed = (data.pets_allowed as boolean) ?? false;
  const petTypes = (data.pet_types as string[]) || [];

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <PawPrint className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Pet Policy</h2>
          <p className="text-sm text-muted-foreground">Define your pet acceptance and fees</p>
        </div>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle>Are pets allowed?</CardTitle>
            <p className="text-sm text-muted-foreground">
              Toggle on to allow pets at your property
            </p>
          </div>
          <Switch
            checked={petsAllowed}
            onCheckedChange={(v) => onChange("pets_allowed", v)}
            disabled={loading}
          />
        </CardHeader>
      </Card>

      {!petsAllowed && (
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>Pets are currently not allowed.</AlertDescription>
        </Alert>
      )}

      {petsAllowed && (
        <div className="space-y-6">
          <div>
            <label className="text-sm font-medium mb-2 block">Allowed Pet Types</label>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {petOptions.map((option) => (
                <label
                  key={option.value}
                  className="flex items-center gap-3 p-3 border rounded-lg hover:bg-accent/50 cursor-pointer"
                >
                  <Checkbox
                    checked={petTypes.includes(option.value)}
                    onCheckedChange={(checked) => {
                      const updated = checked
                        ? [...petTypes, option.value]
                        : petTypes.filter((t) => t !== option.value);
                      onChange("pet_types", updated);
                    }}
                    disabled={loading}
                  />
                  <span className="text-sm">{option.label}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Max Pets</label>
              <Input
                type="number"
                min="1"
                value={data.max_pets || 1}
                onChange={(e) => onChange("max_pets", Number.parseInt(e.target.value))}
                disabled={loading}
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Pet Fee (per booking)</label>
              <div className="flex">
                <span className="inline-flex items-center px-3 bg-muted border border-r-0 rounded-l">
                  $
                </span>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.pet_fee || 0}
                  onChange={(e) => onChange("pet_fee", Number(e.target.value))}
                  className="rounded-l-none"
                  disabled={loading}
                />
              </div>
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Pet Damage Deposit</label>
              <div className="flex">
                <span className="inline-flex items-center px-3 bg-muted border border-r-0 rounded-l">
                  $
                </span>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.pet_deposit || 0}
                  onChange={(e) => onChange("pet_deposit", Number(e.target.value))}
                  placeholder="Optional - refundable"
                  className="rounded-l-none"
                  disabled={loading}
                />
              </div>
            </div>
          </div>

          <div>
            <label className="text-sm font-medium mb-1 block">Size Restrictions</label>
            <Textarea
              value={data.pet_size_restrictions || ""}
              onChange={(e) => onChange("pet_size_restrictions", e.target.value)}
              placeholder="e.g., 'Dogs must be under 25 lbs'"
              rows={2}
              disabled={loading}
            />
          </div>

          <div>
            <label className="text-sm font-medium mb-1 block">Pet Rules & Expectations</label>
            <Textarea
              value={data.pet_rules || ""}
              onChange={(e) => onChange("pet_rules", e.target.value)}
              placeholder="e.g., 'Pets not allowed on furniture', 'Must be crated when away'"
              rows={4}
              disabled={loading}
            />
          </div>
        </div>
      )}
    </div>
  );
}

export default PetPolicySection;
