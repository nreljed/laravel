import { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

export function CurrencySwitcher() {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { activeCurrencies, selectedCurrency } = usePage<any>().props;
  const [loading, setLoading] = useState(false);

  const list: Array<{ code: string; name: string; symbol: string }> = activeCurrencies || [];

  if (list.length < 2) return null;

  const handleChange = (code: string) => {
    setLoading(true);
    router.post(
      route("store.currency.switch"),
      { currency: code },
      {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => setLoading(false),
      },
    );
  };

  return (
    <Select value={selectedCurrency} onValueChange={handleChange} disabled={loading}>
      <SelectTrigger className="w-[80px] h-8 text-xs border-border/50">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {list.map((c) => (
          <SelectItem key={c.code} value={c.code}>
            {c.symbol} {c.code}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
