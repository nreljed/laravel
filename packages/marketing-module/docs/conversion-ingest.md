# Conversion ingest contract

Status: implemented
Parent: [roadmap.md](roadmap.md)
Related: [analytics.md](analytics.md)

## Ownership

The shop owns orders, payment state and revenue. The marketing module owns
tracked links and clicks. A conversion is a shop-reported fact attributed to a
tracked link; the module stores the reference and the value the shop reports,
but it does not calculate or own revenue.

Attribution is **last-click by default**. The shop supplies the click that
brought the visitor, or the link whose latest click should be used.

## Contract

The shop calls `Nreljed\MarketingModule\Contracts\ConversionIngest`:

```php
$conversions = app(\Nreljed\MarketingModule\Contracts\ConversionIngest::class);

$conversions->record([
    'short_code' => 'abc12345',
    'source' => 'shop',
    'event_type' => 'order.paid',
    'external_reference' => (string) $order->id,
    'idempotency_key' => 'shop:order.paid:'.$order->id,
    'value' => $order->total,
    'currency' => $order->currency,
    'occurred_at' => $order->paid_at,
    'metadata' => ['order_id' => $order->id],
]);
```

Accepted payload keys:

| Key | Required | Meaning |
|-----|----------|---------|
| `marketing_link_id` or `link_id` | one of link/click | Marketing link primary key |
| `short_code` | one of link/click | Tracked link short code |
| `marketing_link_click_id` or `click_id` | one of link/click | Exact click to attribute |
| `source` | no, default `shop` | Reporting source |
| `event_type` | no, default `conversion` | Shop event name |
| `external_reference` | recommended | Shop order/payment reference |
| `idempotency_key` | recommended | Replay-safe key; defaults to `source:external_reference` |
| `value` | no | Reported monetary value |
| `currency` | no | Three-letter ISO currency code |
| `occurred_at` | no, default now | When the conversion happened |
| `metadata` | no | Small JSON reference only |

The call is idempotent when `idempotency_key` or `external_reference` is
supplied. Replaying the same key returns the original conversion.

## Attribution

- If `click_id` is supplied, the conversion is attributed to that click's
  link.
- If only a link is supplied, the latest click on that link at or before
  `occurred_at` is used.
- If neither a link nor a click can be resolved, the conversion is stored
  unattributed and does not appear in link reporting.

Last-click attribution means the most recent tracked click wins. Any other
model is an explicit, documented decision.

## Reporting

`MarketingAnalytics` counts attributed conversions by link inside the selected
period. The period uses `occurred_at`, not the original click time. CSV
exports include the same count.

## Limits

- The module does not verify payment state, order totals or customer identity.
- The reported value is shop-provided and may be null.
- Unattributed conversions are stored for audit but are not shown in link
  reports.
- Revenue aggregation, refunds and multi-touch attribution remain shop-owned.
