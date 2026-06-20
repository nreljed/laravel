export interface PriceBreakdown {
  nightlyRate: number;
  nights: number;
  subtotal: number;
  cleaningFee: number;
  serviceFee: number;
  damageDeposit: number;
  total: number;
  currency: string;
}

export function calculateNights(startDate: string, endDate: string): number {
  if (!startDate || !endDate) return 0;
  const start = new Date(startDate);
  const end = new Date(endDate);
  const diff = end.getTime() - start.getTime();
  return Math.max(0, Math.round(diff / (1000 * 60 * 60 * 24)));
}

export function calculatePrice(
  nightlyRate: number,
  nights: number,
  cleaningFee: number = 0,
  serviceFee: number = 0,
  damageDeposit: number = 0,
): Omit<PriceBreakdown, "currency"> {
  const subtotal = nightlyRate * nights;
  return {
    nightlyRate,
    nights,
    subtotal,
    cleaningFee,
    serviceFee,
    damageDeposit,
    total: subtotal + cleaningFee + serviceFee + damageDeposit,
  };
}

export function calculatePriceFromDates(
  nightlyRate: number,
  startDate: string,
  endDate: string,
  cleaningFee: number = 0,
  serviceFee: number = 0,
  damageDeposit: number = 0,
): Omit<PriceBreakdown, "currency"> {
  const nights = calculateNights(startDate, endDate);
  return calculatePrice(nightlyRate, nights, cleaningFee, serviceFee, damageDeposit);
}
