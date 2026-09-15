import { router } from "@inertiajs/react";
import { useState } from "react";
import { Languages } from "lucide-react";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@nreljed/platform/Components/ui/select";
import { useCurrency } from "../lib/currency";

export default function CurrencySwitcher() {
    const { currencies, selectedCode } = useCurrency();
    const [switching, setSwitching] = useState(false);

    if (!currencies || currencies.length === 0) {
        return null;
    }

    const handleChange = (code: string) => {
        if (code === selectedCode) return;
        setSwitching(true);
        router.post(
            route("store.currency.switch"),
            { currency: code },
            {
                preserveScroll: true,
                onFinish: () => setSwitching(false),
            },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <Languages className="h-3.5 w-3.5 text-muted-foreground" />
            <Select
                value={selectedCode ?? undefined}
                onValueChange={handleChange}
                disabled={switching}
            >
                <SelectTrigger
                    size="sm"
                    className="h-7 gap-1 border-0 bg-transparent px-2 text-xs shadow-none focus-visible:ring-0 [&>span]:gap-1"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {currencies.map((c) => (
                        <SelectItem key={c.code} value={c.code}>
                            {c.symbol} {c.code} · {c.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
