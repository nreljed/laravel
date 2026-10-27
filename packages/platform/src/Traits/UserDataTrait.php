<?php

namespace Nreljed\Platform\Traits;

use Illuminate\Support\Facades\Storage;

trait UserDataTrait
{
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->hasAvatarFile()) {
            return null;
        }

        $url = Storage::disk('avatars')->url($this->avatar);

        if (! app()->runningInConsole() && request()->server->has('HTTP_HOST')) {
            $request = request();
            $currentOrigin = str($request->getSchemeAndHttpHost());
            $configuredOrigin = str(parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST));

            if ($currentOrigin->value() !== $configuredOrigin->value()) {
                $url = str_replace($configuredOrigin->value(), $currentOrigin->value(), $url);
            }
        }

        return $url;
    }

    /**
     * Whether the stored avatar filename still resolves to a file.
     *
     * Seeded demo accounts and manually removed uploads leave dangling
     * filenames in `users.avatar`. Returning a URL for a missing file made
     * every page that lists those users request a 404 (one per row), so the
     * accessor degrades to null and the UI falls back to initials.
     */
    public function hasAvatarFile(): bool
    {
        if (! $this->avatar) {
            return false;
        }

        return Storage::disk('avatars')->exists($this->avatar);
    }

    public function socialLinksWithDefaults(): array
    {
        $defaults = [
            'twitter' => null,
            'facebook' => null,
            'instagram' => null,
            'linkedin' => null,
            'tiktok' => null,
            'youtube' => null,
            'whatsapp' => null,
        ];

        $links = $this->social_links ?? [];

        return array_merge($defaults, $links);
    }

    public function profileData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'social_links' => $this->socialLinksWithDefaults(),
            'phone' => $this->phone,
        ];
    }
}
