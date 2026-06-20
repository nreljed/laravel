import { ScheduleSection } from "./ScheduleSection";
import { ConfigurationSection } from "./ConfigurationSection";
import { BookingModeSection } from "./BookingModeSection";

interface BookingSectionProps {
  data: Record<string, unknown>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function AvailabilityModeSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <ScheduleSection data={data} onChange={onChange} loading={loading} />
      <ConfigurationSection data={data} onChange={onChange} loading={loading} />
      <BookingModeSection data={data} onChange={onChange} loading={loading} />
    </div>
  );
}

export default AvailabilityModeSection;
