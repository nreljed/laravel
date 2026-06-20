import React from "react";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Label } from "@/Components/ui/label";
import { XCircle, Info, Clock } from "lucide-react";

interface PolicyDef {
  value: string;
  title: string;
  description: string;
  hasCutoffs: boolean;
}

const POLICIES: PolicyDef[] = [
  {
    value: "flexible",
    title: "Flexible",
    description:
      "Guests can cancel up to the configured days before check-in for a full refund. After that, a partial refund applies.",
    hasCutoffs: true,
  },
  {
    value: "moderate",
    title: "Moderate",
    description:
      "Guests can cancel up to the configured days before check-in for a full refund. A partial refund applies between the full cutoff and the partial cutoff.",
    hasCutoffs: true,
  },
  {
    value: "limited",
    title: "Limited",
    description:
      "Guests can cancel up to 14 days before check-in for a full refund. 50% refund if cancelled 7–14 days before. No refund within 7 days.",
    hasCutoffs: true,
  },
  {
    value: "firm",
    title: "Firm",
    description:
      "Guests can cancel up to 30 days before check-in for a full refund. 50% refund if cancelled 7–30 days before. No refund within 7 days.",
    hasCutoffs: true,
  },
  {
    value: "strict",
    title: "Strict (Legacy)",
    description:
      "Guests can cancel up to the configured days before check-in for a partial refund. After that, no refund.",
    hasCutoffs: true,
  },
  {
    value: "long_term",
    title: "Long Term",
    description:
      "For stays of the configured minimum nights or longer. First X nights are non-refundable; remaining nights are refundable if cancelled before the full refund cutoff.",
    hasCutoffs: true,
  },
  {
    value: "non_refundable",
    title: "Non-Refundable",
    description: "No refunds are given after booking is confirmed. Full amount is charged.",
    hasCutoffs: false,
  },
];

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function CancellationPolicySection({ data, onChange, loading }: BookingSectionProps) {
  const policy = data.cancellation_policy || "flexible";
  const isLongTerm = policy === "long_term";
  const isNonRefundable = policy === "non_refundable";

  const handleNum = (field: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
    onChange(field, e.target.value);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <XCircle className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Cancellation & Deposits</h2>
          <p className="text-sm text-muted-foreground">
            Set your cancellation policy and damage deposit requirements
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Cancellation Policy</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted-foreground">
            Choose how refunds are handled when guests cancel their booking.
          </p>
          <div className="space-y-3">
            {POLICIES.map((p) => (
              <label
                key={p.value}
                className={`flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-colors
                  ${
                    policy === p.value
                      ? "border-primary bg-primary/5"
                      : "border-border hover:bg-muted"
                  }`}
              >
                <input
                  type="radio"
                  name="cancellation_policy"
                  value={p.value}
                  checked={policy === p.value}
                  onChange={(e) => onChange("cancellation_policy", e.target.value)}
                  className="mt-1"
                  disabled={loading}
                />
                <div>
                  <p className="font-medium">{p.title}</p>
                  <p className="text-sm text-muted-foreground mt-0.5">{p.description}</p>
                </div>
              </label>
            ))}
          </div>

          <Alert className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
            <Clock className="h-4 w-4 text-blue-600 dark:text-blue-400" />
            <AlertDescription className="text-blue-700 dark:text-blue-300">
              All bookings include a <strong>24-hour free cancellation window</strong> after
              confirmation. Guests can cancel for a full refund within 24 hours of booking, provided
              the booking was made at least 7 days before check-in. This applies regardless of the
              selected policy.
            </AlertDescription>
          </Alert>

          {isNonRefundable && (
            <p className="text-xs text-muted-foreground">
              Non-refundable bookings receive no refund regardless of cancellation timing.
            </p>
          )}

          {isLongTerm && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-lg border border-border/60 bg-muted/30">
              <div>
                <Label className="text-sm font-medium">Minimum Nights</Label>
                <p className="text-xs text-muted-foreground mb-1">
                  Stays this length or longer qualify for long-term policy.
                </p>
                <Input
                  type="number"
                  min="1"
                  value={data.long_term_min_nights ?? 28}
                  onChange={handleNum("long_term_min_nights")}
                  disabled={loading}
                />
              </div>
              <div>
                <Label className="text-sm font-medium">Non-Refundable Nights</Label>
                <p className="text-xs text-muted-foreground mb-1">
                  First X nights are non-refundable; remaining nights may be refunded.
                </p>
                <Input
                  type="number"
                  min="0"
                  value={data.long_term_nonrefundable_nights ?? 30}
                  onChange={handleNum("long_term_nonrefundable_nights")}
                  disabled={loading}
                />
              </div>
            </div>
          )}

          {!isNonRefundable && !isLongTerm && (
            <div className="space-y-4 p-4 rounded-lg border border-border/60 bg-muted/30">
              <p className="text-sm font-medium">Cutoff Dates</p>
              <p className="text-xs text-muted-foreground">
                Configure the days before check-in when refund tiers change. Leave empty to use
                defaults.
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <Label className="text-sm font-medium">Full Refund Cutoff (days)</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    Full refund when cancelled this many+ days before check-in.
                  </p>
                  <Input
                    type="number"
                    min="0"
                    value={data.cancellation_full_refund_days ?? ""}
                    onChange={handleNum("cancellation_full_refund_days")}
                    disabled={loading}
                    placeholder={
                      policy === "flexible"
                        ? "Default: 1"
                        : policy === "moderate"
                          ? "Default: 5"
                          : policy === "limited"
                            ? "Default: 14"
                            : policy === "firm"
                              ? "Default: 30"
                              : "Default: 7"
                    }
                  />
                </div>
                <div>
                  <Label className="text-sm font-medium">Partial Refund Cutoff (days)</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    Cancel this many+ days before to qualify for partial refund.
                  </p>
                  <Input
                    type="number"
                    min="0"
                    value={data.cancellation_partial_refund_cutoff_days ?? ""}
                    onChange={handleNum("cancellation_partial_refund_cutoff_days")}
                    disabled={loading}
                    placeholder={
                      policy === "flexible"
                        ? "Default: 0 (disabled)"
                        : policy === "moderate"
                          ? "Default: 1"
                          : policy === "limited" || policy === "firm"
                            ? "Default: 7"
                            : "N/A"
                    }
                  />
                </div>
                <div>
                  <Label className="text-sm font-medium">Partial Refund %</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    Percentage refunded in the partial window.
                  </p>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    value={data.cancellation_partial_refund_percent ?? ""}
                    onChange={handleNum("cancellation_partial_refund_percent")}
                    disabled={loading}
                    placeholder="Default: 50"
                  />
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Damage Deposit</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium text-sm">Require Damage Deposit</p>
              <p className="text-sm text-muted-foreground">
                Collect a refundable deposit before check-in
              </p>
            </div>
            <Switch
              checked={data.require_damage_deposit ?? false}
              onCheckedChange={(v) => onChange("require_damage_deposit", v)}
              disabled={loading}
            />
          </div>

          {data.require_damage_deposit && (
            <div className="space-y-4 p-4 rounded-lg border border-border/60 bg-muted/30">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <Label className="text-sm font-medium">Collection Method</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    How the deposit is collected from the guest.
                  </p>
                  <Select
                    value={data.deposit_collection_method || "credit_card_hold"}
                    onValueChange={(v) => onChange("deposit_collection_method", v)}
                    disabled={loading}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="credit_card_hold">Credit Card Hold</SelectItem>
                      <SelectItem value="separate_charge">Separate Charge</SelectItem>
                      <SelectItem value="cash_at_arrival">Cash on Arrival</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div>
                  <Label className="text-sm font-medium">Deposit Type</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    Fixed or percentage-based deposit.
                  </p>
                  <Select
                    value={data.deposit_type || "fixed_amount"}
                    onValueChange={(v) => onChange("deposit_type", v)}
                    disabled={loading}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="fixed_amount">Fixed Amount</SelectItem>
                      <SelectItem value="percentage_of_total">
                        Percentage of Booking Total
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {(data.deposit_type || "fixed_amount") === "fixed_amount" ? (
                <div>
                  <Label className="text-sm font-medium">Deposit Amount ($)</Label>
                  <Input
                    type="number"
                    step="0.01"
                    min="0"
                    value={data.damage_deposit_amount || ""}
                    onChange={(e) => onChange("damage_deposit_amount", e.target.value)}
                    disabled={loading}
                    placeholder="0.00"
                  />
                </div>
              ) : (
                <div>
                  <Label className="text-sm font-medium">Deposit Percentage (%)</Label>
                  <p className="text-xs text-muted-foreground mb-1">
                    Percentage of the total booking price.
                  </p>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    value={data.deposit_percentage || ""}
                    onChange={(e) => onChange("deposit_percentage", e.target.value)}
                    disabled={loading}
                    placeholder="50"
                  />
                </div>
              )}

              <div>
                <Label className="text-sm font-medium">Refund Timeline (days after checkout)</Label>
                <p className="text-xs text-muted-foreground mb-1">
                  How soon after checkout the deposit is returned (if no damages).
                </p>
                <Input
                  type="number"
                  min="0"
                  max="90"
                  value={data.deposit_refund_days ?? ""}
                  onChange={(e) => onChange("deposit_refund_days", e.target.value)}
                  disabled={loading}
                  placeholder="Default: 7"
                />
              </div>

              <div>
                <Label className="text-sm font-medium">Deposit Policy</Label>
                <Textarea
                  value={data.damage_deposit_policy || ""}
                  onChange={(e) => onChange("damage_deposit_policy", e.target.value)}
                  placeholder="Explain how deposits are handled, claim process, and refund timeline"
                  rows={4}
                  disabled={loading}
                />
              </div>
            </div>
          )}

          <div className="pt-4 border-t border-border/60">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Damage Waiver</p>
                <p className="text-sm text-muted-foreground">
                  Guests pay a small non-refundable fee instead of a refundable deposit
                </p>
              </div>
              <Switch
                checked={data.enable_damage_waiver ?? false}
                onCheckedChange={(v) => onChange("enable_damage_waiver", v)}
                disabled={loading}
              />
            </div>
            {data.enable_damage_waiver && (
              <div className="mt-3">
                <Label className="text-sm font-medium">Waiver Fee ($)</Label>
                <p className="text-xs text-muted-foreground mb-1">
                  Flat fee charged to guest in lieu of refundable deposit.
                </p>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.damage_waiver_fee || ""}
                  onChange={(e) => onChange("damage_waiver_fee", e.target.value)}
                  disabled={loading}
                  placeholder="0.00"
                />
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <Alert>
        <Info className="h-4 w-4" />
        <AlertDescription>
          Your cancellation policy and deposit terms will be displayed to guests before they confirm
          their booking.
        </AlertDescription>
      </Alert>
    </div>
  );
}

export default CancellationPolicySection;
