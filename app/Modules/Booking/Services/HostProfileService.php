<?php

namespace App\Modules\Booking\Services;

use App\Models\User;

class HostProfileService
{
    public function resolveHostPhone(?int $hostId): string
    {
        if (! $hostId) {
            return '';
        }
        $user = User::find($hostId);
        if (! $user) {
            return '';
        }
        $phoneNumbers = $user->phone;
        if (empty($phoneNumbers)) {
            return '';
        }
        $decoded = is_string($phoneNumbers) ? json_decode($phoneNumbers, true) : $phoneNumbers;
        if (! is_array($decoded) || empty($decoded)) {
            return '';
        }
        $preferred = ['Mobile', 'Work', 'WhatsApp', 'Home'];
        foreach ($preferred as $label) {
            foreach ($decoded as $entry) {
                if (($entry['label'] ?? '') === $label && ! empty($entry['phone'])) {
                    return $entry['phone'];
                }
            }
        }

        return $decoded[0]['phone'] ?? '';
    }

    public function resolveHostPhoneNumbers(?int $hostId): array
    {
        if (! $hostId) {
            return [];
        }
        $user = User::find($hostId);
        if (! $user) {
            return [];
        }
        $raw = $user->phone;
        if (empty($raw)) {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    public function resolveHostEmail(?int $hostId): string
    {
        if (! $hostId) {
            return '';
        }
        $user = User::find($hostId);
        if (! $user) {
            return '';
        }

        return $user->contact_email ?: $user->email;
    }

    public function resolveHostAvatar(?int $hostId): string
    {
        if (! $hostId) {
            return '';
        }
        $user = User::find($hostId);
        if (! $user) {
            return '';
        }
        if ($user->avatar_url) {
            return $user->avatar_url;
        }
        $gravatar = 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($user->email))).'?d=mp';

        return $gravatar;
    }

    public function resolveHostBio(?int $hostId): string
    {
        if (! $hostId) {
            return '';
        }
        $user = User::find($hostId);
        if (! $user) {
            return '';
        }

        return $user->bio ?: $user->description ?? '';
    }

    public function resolveHostSocialMedia(?int $hostId): array
    {
        if (! $hostId) {
            return [];
        }
        $user = User::find($hostId);
        if (! $user) {
            return [];
        }
        $raw = $user->social_links;
        if (empty($raw)) {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    public function resolveAll(int $hostId): array
    {
        return [
            'host_phone' => $this->resolveHostPhone($hostId),
            'host_phone_numbers' => $this->resolveHostPhoneNumbers($hostId),
            'host_email' => $this->resolveHostEmail($hostId),
            'host_avatar' => $this->resolveHostAvatar($hostId),
            'host_bio' => $this->resolveHostBio($hostId),
            'host_social_media' => $this->resolveHostSocialMedia($hostId),
        ];
    }
}
