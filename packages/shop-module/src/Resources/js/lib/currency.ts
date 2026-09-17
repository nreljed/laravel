import { usePage } from "@inertiajs/react";
import type { PageProps } from "@nreljed/platform/types";

export interface CurrencyInfo {
    code: string;
    name: string;
    symbol: string;
    exchange_rate: number;
    decimal_places: number;
    is_default: boolean;
}

interface CurrencyFormat {
    position: string;
    thousands_separator: string;
    decimal_separator: string;
    decimals: number;
}

interface CurrencySharedData {
    currencies?: CurrencyInfo[];
    selectedCurrency?: string;
    currencyPosition?: string;
    currencyFormat?: CurrencyFormat;
}

function round(value: number, decimals: number): number {
    const factor = 10 ** decimals;
    return Math.round((value + Number.EPSILON) * factor) / factor;
}

const FALLBACK_CURRENCY: CurrencyInfo = {
    code: "EUR",
    name: "Euro",
    symbol: "€",
    exchange_rate: 1,
    decimal_places: 2,
    is_default: true,
};

const FALLBACK_FORMAT: CurrencyFormat = {
    position: "before",
    thousands_separator: ",",
    decimal_separator: ".",
    decimals: 2,
};

export function useCurrency() {
    const { props } = usePage<PageProps<{ sharedData?: CurrencySharedData }>>();
    const shared = props.sharedData ?? {};
    const currencies = shared.currencies ?? [];
    const selectedCode = shared.selectedCurrency;
    const formatSettings = shared.currencyFormat ?? FALLBACK_FORMAT;

    const active =
        currencies.find((c) => c.code === selectedCode) ??
        currencies.find((c) => c.is_default) ??
        currencies[0] ??
        FALLBACK_CURRENCY;

    const convert = (amount: number | string | null | undefined): number => {
        const value = Number(amount);
        if (!Number.isFinite(value)) return 0;
        const rate = active.exchange_rate ?? 1;
        return rate === 1 ? value : round(value * rate, active.decimal_places ?? 2);
    };

    const formatNumber = (value: number, decimals: number): string => {
        const [intPart, decPart] = value.toFixed(decimals).split(".");
        const grouped = (intPart ?? "").replace(/\B(?=(\d{3})+(?!\d))/g, formatSettings.thousands_separator);
        return decPart ? `${grouped}${formatSettings.decimal_separator}${decPart}` : grouped;
    };

    const format = (
        value: number | string | null | undefined,
        options: { symbol?: boolean; decimals?: number } = {},
    ): string => {
        const amount = Number(value);
        if (!Number.isFinite(amount)) return "";
        const decimals = options.decimals ?? formatSettings.decimals ?? active.decimal_places ?? 2;
        const formatted = formatNumber(amount, decimals);
        if (options.symbol === false) return formatted;

        const symbol = active.symbol;
        switch (formatSettings.position) {
            case "after":
                return `${formatted}${symbol}`.trim();
            case "before_space":
                return `${symbol} ${formatted}`.trim();
            case "after_space":
                return `${formatted} ${symbol}`.trim();
            default:
                return `${symbol}${formatted}`;
        }
    };

    const money = (amount: number | string | null | undefined): string =>
        format(convert(amount));

    return { currencies, selectedCode, active, convert, format, money };
}
