import { RulesSection } from "./RulesSection";
import { PetPolicySection } from "./PetPolicySection";
import { CancellationPolicySection } from "./CancellationPolicySection";
import { BookingPoliciesSection } from "./BookingPoliciesSection";
import { SafetySection } from "./SafetySection";

interface BookingSectionProps {
  data: Record<string, unknown>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function RulesTabSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <RulesSection data={data} onChange={onChange} loading={loading} />
      <div className="border-t pt-6">
        <PetPolicySection data={data} onChange={onChange} loading={loading} />
      </div>
      <div className="border-t pt-6">
        <CancellationPolicySection data={data} onChange={onChange} loading={loading} />
      </div>
      <div className="border-t pt-6">
        <SafetySection data={data} onChange={onChange} loading={loading} />
      </div>
      <div className="border-t pt-6">
        <BookingPoliciesSection data={data} onChange={onChange} loading={loading} />
      </div>
    </div>
  );
}

export default RulesTabSection;
