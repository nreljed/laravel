<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Throwable;

class TrackedLinkService
{
    public function ensureForTarget(
        SocialPostTarget $target,
        SocialPost $post,
        ?SocialAccount $account = null,
    ): ?MarketingLink {
        if (! (bool) $post->track_clicks) {
            return null;
        }

        $destination = $this->normaliseDestination($post->link);

        if ($destination === null) {
            return null;
        }

        $account ??= $target->account;
        $platform = trim((string) ($account?->platform ?? 'unknown'));
        $post->loadMissing('campaign');

        $link = MarketingLink::query()->firstOrNew([
            'social_post_target_id' => $target->getKey(),
        ]);

        $link->fill([
            'social_post_id' => $post->getKey(),
            'destination_url' => $destination,
            'utm_source' => $platform !== '' ? $platform : null,
            'utm_medium' => 'social',
            'utm_campaign' => $this->campaignSlug($post),
            'utm_content' => 'post-'.$post->getKey().'-'.$platform,
            'utm_term' => null,
            'is_active' => true,
        ]);

        if (! $link->exists) {
            $link->short_code = $this->uniqueCode();
        }

        $link->save();

        return $link;
    }

    public function trackedUrl(MarketingLink $link): string
    {
        $base = $this->baseUrl();
        $prefix = trim((string) config('marketing.links.prefix', 'm'), '/');

        return $base.'/'.($prefix !== '' ? $prefix.'/' : '').$link->short_code;
    }

    public function findActive(string $shortCode): ?MarketingLink
    {
        return MarketingLink::query()
            ->where('short_code', trim($shortCode))
            ->where('is_active', true)
            ->first();
    }

    public function destinationUrl(MarketingLink $link): ?string
    {
        return $this->buildUrl($link->destination_url, [
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_content' => $link->utm_content,
            'utm_term' => $link->utm_term,
        ]);
    }

    public function recordClick(MarketingLink $link, Request $request): ?MarketingLinkClick
    {
        try {
            return DB::transaction(function () use ($link, $request): MarketingLinkClick {
                $click = MarketingLinkClick::create([
                    'marketing_link_id' => $link->getKey(),
                    'clicked_at' => now(),
                    'ip_hash' => $this->hashIp($request->ip()),
                    'user_agent' => $this->truncate($request->userAgent(), 512),
                    'referer' => $this->truncate($request->headers->get('referer'), 2048),
                    'country' => null,
                ]);

                MarketingLink::query()
                    ->whereKey($link->getKey())
                    ->increment('clicks_count', 1, ['last_clicked_at' => now()]);

                return $click;
            });
        } catch (Throwable $e) {
            $this->logFailure('marketing.link_click_failed', $link, $e);

            return null;
        }
    }

    public function normaliseDestination(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            throw new InvalidArgumentException('Tracked links require an absolute http or https URL.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Tracked links require an absolute http or https URL.');
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Tracked links only support http and https destinations.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Tracked links cannot contain embedded credentials.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The tracked destination is not a valid URL.');
        }

        return $url;
    }

    protected function buildUrl(string $url, array $utm): ?string
    {
        try {
            $destination = $this->normaliseDestination($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($destination === null) {
            return null;
        }

        $parts = parse_url($destination);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($utm as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $query[$key] = $value;
            }
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$path
            .($queryString !== '' ? '?'.$queryString : '')
            .$fragment;
    }

    protected function campaignSlug(SocialPost $post): ?string
    {
        $name = trim((string) ($post->campaign?->name ?? ''));

        if ($name === '') {
            return null;
        }

        return Str::slug($name) ?: null;
    }

    protected function uniqueCode(): string
    {
        $length = max(4, min(16, (int) config('marketing.links.code_length', 8)));

        do {
            $code = Str::lower(Str::random($length));
        } while (MarketingLink::query()->where('short_code', $code)->exists());

        return $code;
    }

    protected function baseUrl(): string
    {
        $base = trim((string) (config('marketing.links.base_url') ?: config('app.url')));
        $base = rtrim($base, '/');

        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Tracked links require a valid application URL.');
        }

        return $base;
    }

    protected function hashIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return null;
        }

        $key = (string) config('app.key');

        return $key !== ''
            ? hash_hmac('sha256', $ip, $key)
            : hash('sha256', $ip);
    }

    protected function truncate(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $length, '');
    }

    protected function logFailure(string $event, MarketingLink $link, Throwable $e): void
    {
        try {
            Log::warning($event, [
                'marketing_link_id' => $link->getKey(),
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
        }
    }
}
