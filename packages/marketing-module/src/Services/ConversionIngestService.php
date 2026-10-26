<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Nreljed\MarketingModule\Contracts\ConversionIngest;
use Nreljed\MarketingModule\Models\MarketingConversion;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;

class ConversionIngestService implements ConversionIngest
{
    public function record(array $payload): MarketingConversion
    {
        $link = $this->resolveLink($payload);
        $click = $this->resolveClick($payload, $link);

        if ($link === null && $click === null) {
            throw new InvalidArgumentException('A conversion requires a link, short code or click reference.');
        }

        if ($link === null && $click !== null) {
            $link = $click->link;
        }

        if ($link !== null && $click !== null && (int) $click->marketing_link_id !== (int) $link->getKey()) {
            throw new InvalidArgumentException('The click does not belong to the supplied link.');
        }

        $source = trim((string) ($payload['source'] ?? 'shop'));
        $source = $source !== '' ? $source : 'shop';

        $externalReference = $this->nullableString($payload['external_reference'] ?? null);
        $idempotencyKey = $this->nullableString($payload['idempotency_key'] ?? null);

        if ($idempotencyKey === null && $externalReference !== null) {
            $idempotencyKey = $source.':'.$externalReference;
        }

        $occurredAt = $this->parseDate($payload['occurred_at'] ?? null);

        $value = $this->normaliseValue($payload['value'] ?? null);
        $currency = $this->normaliseCurrency($payload['currency'] ?? null);

        $data = [
            'marketing_link_id' => $link?->getKey(),
            'marketing_link_click_id' => $click?->getKey(),
            'source' => $source,
            'event_type' => trim((string) ($payload['event_type'] ?? 'conversion')) ?: 'conversion',
            'external_reference' => $externalReference,
            'idempotency_key' => $idempotencyKey,
            'value' => $value,
            'currency' => $currency,
            'occurred_at' => $occurredAt,
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
        ];

        if ($idempotencyKey !== null) {
            return MarketingConversion::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                $data,
            );
        }

        return MarketingConversion::create($data);
    }

    private function resolveLink(array $payload): ?MarketingLink
    {
        if (isset($payload['marketing_link_id']) || isset($payload['link_id'])) {
            $id = (int) ($payload['marketing_link_id'] ?? $payload['link_id']);

            return MarketingLink::query()->find($id);
        }

        $shortCode = $this->nullableString($payload['short_code'] ?? null);

        if ($shortCode !== null) {
            return MarketingLink::query()
                ->where('short_code', $shortCode)
                ->first();
        }

        return null;
    }

    private function resolveClick(array $payload, ?MarketingLink $link): ?MarketingLinkClick
    {
        if (isset($payload['marketing_link_click_id']) || isset($payload['click_id'])) {
            $id = (int) ($payload['marketing_link_click_id'] ?? $payload['click_id']);

            return MarketingLinkClick::query()->find($id);
        }

        if ($link === null) {
            return null;
        }

        $occurredAt = $this->parseDate($payload['occurred_at'] ?? null);

        return MarketingLinkClick::query()
            ->where('marketing_link_id', $link->getKey())
            ->where('clicked_at', '<=', $occurredAt)
            ->orderByDesc('clicked_at')
            ->first();
    }

    private function parseDate(mixed $value): CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                throw new InvalidArgumentException('occurred_at must be a valid date or datetime.');
            }
        }

        return CarbonImmutable::now();
    }

    private function normaliseValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('value must be numeric.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normaliseCurrency(mixed $value): ?string
    {
        $currency = $this->nullableString($value);

        if ($currency === null) {
            return null;
        }

        $currency = strtoupper($currency);

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('currency must be a three-letter ISO code.');
        }

        return $currency;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
