<?php

namespace Nreljed\BookingModule\Booking\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Nreljed\BookingModule\Properties\Models\Property;

class BookingIcalService
{
    public const MAX_FEEDS_PER_PROPERTY = 10;

    public const MAX_EVENTS_PER_FEED = 500;

    public const MAX_FEED_SIZE = 1048576;

    public function parseIcal(string $data): array
    {
        $data = str_replace(["\r\n", "\r"], "\n", $data);
        $data = preg_replace('/\n[ \t]/', '', $data);

        $events = [];
        preg_match_all('/BEGIN:VEVENT\n(.*?)\nEND:VEVENT/s', $data, $matches);

        foreach ($matches[1] as $block) {
            $event = $this->parseVevent($block);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function parseVevent(string $block): ?array
    {
        $dtstart = $this->extractProperty($block, 'DTSTART');
        $dtend = $this->extractProperty($block, 'DTEND');
        $summary = $this->extractProperty($block, 'SUMMARY') ?: 'Imported';
        $uid = $this->extractProperty($block, 'UID') ?: Str::uuid()->toString();

        if ($dtstart === null || $dtend === null) {
            return null;
        }

        $startDate = $this->parseIcalDate($dtstart);
        $endDate = $this->parseIcalDate($dtend);

        if ($startDate === null || $endDate === null) {
            return null;
        }

        $endDate->modify('-1 day');

        if ($endDate < $startDate) {
            return null;
        }

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
            'summary' => $summary,
            'uid' => $uid,
        ];
    }

    private function extractProperty(string $block, string $name): ?string
    {
        if (preg_match('/^'.$name.'(?:;[^:\n]*)?:(.*)$/m', $block, $m)) {
            $value = preg_replace(
                ['/\\\\;/', '/\\\\,/', '/\\\\[nN]/', '/\\\\\\\\/'],
                [';', ',', "\n", '\\'],
                $m[1]
            );

            return $value;
        }

        return null;
    }

    private function parseIcalDate(string $value): ?Carbon
    {
        $value = trim($value);
        $value = rtrim($value, 'Z');

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return Carbon::parse("{$m[1]}-{$m[2]}-{$m[3]}");
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $value, $m)) {
            return Carbon::parse("{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}");
        }

        return null;
    }

    public function downloadFeed(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['Accept' => 'text/calendar, application/octet-stream'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            if (strlen($body) > self::MAX_FEED_SIZE) {
                return null;
            }

            return $body;
        } catch (HttpClientException) {
            return null;
        }
    }

    public function getFeeds(int $propertyId): array
    {
        $property = Property::find($propertyId);
        if (! $property) {
            return [];
        }

        $metadata = $property->metadata ?? [];

        return $metadata['booking_ical_feeds'] ?? [];
    }

    private function getFeed(int $propertyId, string $feedId): ?array
    {
        foreach ($this->getFeeds($propertyId) as $feed) {
            if (($feed['id'] ?? '') === $feedId) {
                return $feed;
            }
        }

        return null;
    }

    private function saveFeeds(int $propertyId, array $feeds): void
    {
        $property = Property::findOrFail($propertyId);
        $metadata = $property->metadata ?? [];
        $metadata['booking_ical_feeds'] = $feeds;
        $property->metadata = $metadata;
        $property->save();
    }

    private function getBlockedDates(int $propertyId): array
    {
        $property = Property::find($propertyId);
        if (! $property) {
            return [];
        }

        $metadata = $property->metadata ?? [];

        return $metadata['booking_blocked_dates'] ?? [];
    }

    private function saveBlockedDates(int $propertyId, array $blocks): void
    {
        $property = Property::findOrFail($propertyId);
        $metadata = $property->metadata ?? [];
        $metadata['booking_blocked_dates'] = $blocks;
        $property->metadata = $metadata;
        $property->save();
    }

    private function generateFeedId(): string
    {
        return 'feed_'.bin2hex(random_bytes(8));
    }

    private function generateFeedName(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'iCal Feed';

        return ucfirst(str_replace(['www.', '.com', '.net', '.org'], '', $host));
    }

    public function importFeed(int $propertyId, string $url, string $name = ''): array
    {
        $feeds = $this->getFeeds($propertyId);

        if (count($feeds) >= self::MAX_FEEDS_PER_PROPERTY) {
            return [
                'success' => false,
                'error' => 'Maximum '.self::MAX_FEEDS_PER_PROPERTY.' feeds per property.',
            ];
        }

        $url = trim($url);
        foreach ($feeds as $feed) {
            if (($feed['url'] ?? '') === $url) {
                return ['success' => false, 'error' => 'This feed URL is already imported.'];
            }
        }

        $icalData = $this->downloadFeed($url);

        if ($icalData === null) {
            return ['success' => false, 'error' => 'Failed to download iCal feed.'];
        }

        $events = $this->parseIcal($icalData);

        if (empty($events)) {
            return ['success' => false, 'error' => 'No events found in iCal feed.'];
        }

        if (count($events) > self::MAX_EVENTS_PER_FEED) {
            $events = array_slice($events, 0, self::MAX_EVENTS_PER_FEED);
        }

        $name = $name ?: $this->generateFeedName($url);

        $feedId = $this->generateFeedId();
        $existingBlocks = $this->getBlockedDates($propertyId);

        foreach ($events as $event) {
            $existingBlocks[] = [
                'start' => $event['start'],
                'end' => $event['end'],
                'reason' => $event['summary'],
                'created_at' => now()->toDateTimeString(),
                'source' => 'ical:'.$feedId,
                'source_uid' => $event['uid'],
                'source_type' => 'ical',
            ];
        }

        $this->saveBlockedDates($propertyId, $existingBlocks);

        $feed = [
            'id' => $feedId,
            'url' => $url,
            'name' => $name,
            'last_sync' => now()->toDateTimeString(),
            'last_sync_status' => 'success',
            'last_error' => null,
            'event_count' => count($events),
            'created_at' => now()->toDateTimeString(),
        ];

        $feeds[] = $feed;
        $this->saveFeeds($propertyId, $feeds);

        return [
            'success' => true,
            'feed_id' => $feedId,
            'event_count' => count($events),
        ];
    }

    public function syncFeed(int $propertyId, string $feedId): array
    {
        $feeds = $this->getFeeds($propertyId);
        $feedIndex = null;

        foreach ($feeds as $i => $feed) {
            if (($feed['id'] ?? '') === $feedId) {
                $feedIndex = $i;
                break;
            }
        }

        if ($feedIndex === null) {
            return ['success' => false, 'error' => 'Feed not found.'];
        }

        $feed = $feeds[$feedIndex];

        $sourcePrefix = 'ical:'.$feedId;
        $existingBlocks = $this->getBlockedDates($propertyId);
        $existingBlocks = array_values(
            array_filter(
                $existingBlocks,
                fn ($b) => str_starts_with($b['source'] ?? '', $sourcePrefix) !== 0
            )
        );

        $icalData = $this->downloadFeed($feed['url']);

        if ($icalData === null) {
            $feeds[$feedIndex]['last_sync'] = now()->toDateTimeString();
            $feeds[$feedIndex]['last_sync_status'] = 'error';
            $feeds[$feedIndex]['last_error'] = 'Failed to download feed.';
            $this->saveFeeds($propertyId, $feeds);

            return ['success' => false, 'error' => 'Failed to download iCal feed.'];
        }

        $events = $this->parseIcal($icalData);

        if (count($events) > self::MAX_EVENTS_PER_FEED) {
            $events = array_slice($events, 0, self::MAX_EVENTS_PER_FEED);
        }

        foreach ($events as $event) {
            $existingBlocks[] = [
                'start' => $event['start'],
                'end' => $event['end'],
                'reason' => $event['summary'],
                'created_at' => now()->toDateTimeString(),
                'source' => 'ical:'.$feedId,
                'source_uid' => $event['uid'],
                'source_type' => 'ical',
            ];
        }

        $this->saveBlockedDates($propertyId, $existingBlocks);

        $feeds[$feedIndex]['last_sync'] = now()->toDateTimeString();
        $feeds[$feedIndex]['last_sync_status'] = 'success';
        $feeds[$feedIndex]['last_error'] = null;
        $feeds[$feedIndex]['event_count'] = count($events);
        $this->saveFeeds($propertyId, $feeds);

        return [
            'success' => true,
            'feed_id' => $feedId,
            'event_count' => count($events),
        ];
    }

    public function deleteFeed(int $propertyId, string $feedId): bool
    {
        $feeds = $this->getFeeds($propertyId);
        $found = false;

        $feeds = array_values(
            array_filter(
                $feeds,
                function ($f) use ($feedId, &$found) {
                    if (($f['id'] ?? '') === $feedId) {
                        $found = true;

                        return false;
                    }

                    return true;
                }
            )
        );

        if (! $found) {
            return false;
        }

        $this->saveFeeds($propertyId, $feeds);

        $sourcePrefix = 'ical:'.$feedId;
        $existingBlocks = $this->getBlockedDates($propertyId);
        $existingBlocks = array_values(
            array_filter(
                $existingBlocks,
                fn ($b) => str_starts_with($b['source'] ?? '', $sourcePrefix) !== 0
            )
        );
        $this->saveBlockedDates($propertyId, $existingBlocks);

        return true;
    }

    public function blockDates(int $propertyId, string $start, string $end, string $reason = ''): array
    {
        $existingBlocks = $this->getBlockedDates($propertyId);

        $existingBlocks[] = [
            'start' => $start,
            'end' => $end,
            'reason' => $reason,
            'created_at' => now()->toDateTimeString(),
            'source' => 'manual',
            'source_uid' => '',
            'source_type' => 'manual',
        ];

        $this->saveBlockedDates($propertyId, $existingBlocks);

        return ['success' => true];
    }
}
