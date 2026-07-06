import { useMemo } from "react";
import { usePage } from "@inertiajs/react";
import type { PageProps } from "@inertiajs/core";

interface Currency {
  code: string;
  name: string;
  symbol: string;
  exchange_rate?: number;
  decimal_places?: number;
  is_default?: boolean;
}

export function useCurrency() {
  const { activeCurrencies, selectedCurrency } = usePage<
    PageProps & {
      activeCurrencies: Currency[];
      selectedCurrency: string;
    }
  >().props;

  return useMemo(() => {
    const currencies: Currency[] = activeCurrencies || [];
    const current: Currency = currencies.find((c: Currency) => c.code === selectedCurrency) ||
      currencies.find((c: Currency) => c.code === "EUR") || {
        code: "EUR",
        name: "Euro",
        symbol: "€",
      };
    const defaultCurrency: Currency = currencies.find((c: Currency) => c.is_default) ||
      currencies[0] || { code: "EUR", name: "Euro", symbol: "€" };

    const exchangeRate =
      current.code === defaultCurrency.code
        ? 1
        : current.exchange_rate && defaultCurrency.exchange_rate
          ? current.exchange_rate / defaultCurrency.exchange_rate
          : 1;

    const format = (amount: number): string => {
      const converted = amount * exchangeRate;
      return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: current.code,
        minimumFractionDigits: 2,
        maximumFractionDigits: current.decimal_places ?? 2,
      }).format(converted);
    };

    const convert = (amount: number): number => amount * exchangeRate;

    return { currency: current, defaultCurrency, exchangeRate, format, convert, currencies };
  }, [activeCurrencies, selectedCurrency]);
}
