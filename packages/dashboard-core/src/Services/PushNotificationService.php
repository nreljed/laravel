<?php

namespace Nreljed\DashboardCore\Services;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nreljed\DashboardCore\Models\PushSubscription;
use Nreljed\DashboardCore\Models\Setting;

class PushNotificationService
{
    private const string VAPID_PUBLIC_KEY = 'push_vapid_public_key';

    private const string VAPID_PRIVATE_KEY = 'push_vapid_private_key';

    private const string SUBJECT = 'push_vapid_subject';

    private static ?WebPush $webPush = null;

    public static function ensureVapidKeys(): ?string
    {
        $publicKey = Setting::get(self::VAPID_PUBLIC_KEY);
        $privateKey = Setting::get(self::VAPID_PRIVATE_KEY);

        if ($publicKey && $privateKey) {
            return $publicKey;
        }

        try {
            $keys = self::generateVapidKeys();
        } catch (\RuntimeException $e) {
            logger()->error('[PushNotificationService] Failed to generate VAPID keys: '.$e->getMessage());

            return null;
        }

        Setting::set(self::VAPID_PUBLIC_KEY, $keys['publicKey']);
        Setting::set(self::VAPID_PRIVATE_KEY, $keys['privateKey']);

        return $keys['publicKey'];
    }

    public static function generateVapidKeys(): array
    {
        $curve = @openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($curve !== false) {
            $details = openssl_pkey_get_details($curve);
            if ($details !== false && isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
                return [
                    'publicKey' => self::base64UrlEncode("\x04".$details['ec']['x'].$details['ec']['y']),
                    'privateKey' => self::base64UrlEncode($details['ec']['d']),
                ];
            }
        }

        throw new \RuntimeException('Failed to generate VAPID keys via OpenSSL');
    }

    public static function getVapidPublicKey(): string
    {
        return Setting::get(self::VAPID_PUBLIC_KEY, '');
    }

    public static function deleteVapidKeys(): void
    {
        Setting::set(self::VAPID_PUBLIC_KEY, '');
        Setting::set(self::VAPID_PRIVATE_KEY, '');
        self::$webPush = null;
    }

    public static function saveSubscription(int $userId, array $subscription): bool
    {
        self::deleteSubscription($userId, $subscription['endpoint']);

        $record = new PushSubscription;
        $record->user_id = $userId;
        $record->endpoint = $subscription['endpoint'];
        $record->p256dh_key = $subscription['keys']['p256dh'] ?? '';
        $record->auth_key = $subscription['keys']['auth'] ?? '';

        return $record->save();
    }

    public static function deleteSubscription(int $userId, string $endpoint): bool
    {
        return (bool) PushSubscription::where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete();
    }

    public static function deleteAllSubscriptions(int $userId): bool
    {
        return (bool) PushSubscription::where('user_id', $userId)->delete();
    }

    public static function getSubscriptions(int $userId): array
    {
        return PushSubscription::where('user_id', $userId)
            ->get()
            ->map(fn (PushSubscription $sub) => [
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh_key,
                    'auth' => $sub->auth_key,
                ],
            ])
            ->toArray();
    }

    public static function sendToUser(int $userId, string $title, string $body, string $icon = '', string $url = '/dashboard', ?int $notificationId = null): array
    {
        $subscriptions = self::getSubscriptions($userId);

        if (empty($subscriptions)) {
            return ['success' => 0, 'failure' => 0];
        }

        $webPush = self::getWebPush();
        $payload = self::buildPayload($title, $body, $icon, $url, $notificationId);
        $results = ['success' => 0, 'failure' => 0];

        foreach ($subscriptions as $sub) {
            if (! isset($sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth'])) {
                $results['failure']++;

                continue;
            }

            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['keys']['p256dh'],
                    'authToken' => $sub['keys']['auth'],
                ]);

                $webPush->queueNotification($subscription, $payload);
            } catch (\Throwable $e) {
                logger()->error('[PushNotificationService] Failed to queue push: '.$e->getMessage());
                $results['failure']++;
            }
        }

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $results['success']++;
                } else {
                    $endpoint = $report->getEndpoint();
                    logger()->warning('[PushNotificationService] Push failed: '.($report->getReason() ?? 'unknown').' for '.$endpoint);

                    if ($report->isSubscriptionExpired()) {
                        self::deleteSubscription($userId, $endpoint);
                    }
                    $results['failure']++;
                }
            }
        } catch (\Throwable $e) {
            logger()->error('[PushNotificationService] Flush failed: '.$e->getMessage());
            $results['failure']++;
        }

        return $results;
    }

    public static function sendToAll(string $title, string $body, string $icon = '', string $url = '/dashboard'): array
    {
        $userIds = PushSubscription::distinct()->pluck('user_id')->toArray();

        $total = count($userIds);
        $totalSuccess = 0;
        $totalFailure = 0;

        foreach ($userIds as $userId) {
            $result = self::sendToUser($userId, $title, $body, $icon, $url);
            $totalSuccess += $result['success'];
            $totalFailure += $result['failure'];
        }

        return [
            'total' => $total,
            'success' => $totalSuccess,
            'failure' => $totalFailure,
        ];
    }

    public static function sendToUsers(array $userIds, string $title, string $body, string $icon = '', string $url = '/dashboard'): array
    {
        $total = count($userIds);
        $totalSuccess = 0;
        $totalFailure = 0;

        foreach ($userIds as $userId) {
            $result = self::sendToUser($userId, $title, $body, $icon, $url);
            $totalSuccess += $result['success'];
            $totalFailure += $result['failure'];
        }

        return [
            'total' => $total,
            'success' => $totalSuccess,
            'failure' => $totalFailure,
        ];
    }

    private static function getWebPush(): WebPush
    {
        if (self::$webPush !== null) {
            return self::$webPush;
        }

        $publicKey = self::getVapidPublicKey();
        $privateKey = Setting::get(self::VAPID_PRIVATE_KEY, '');

        if (empty($publicKey) || empty($privateKey)) {
            $publicKey = self::ensureVapidKeys();
            $privateKey = Setting::get(self::VAPID_PRIVATE_KEY, '');
        }

        $subject = Setting::get(self::SUBJECT, config('app.url', 'http://localhost'));

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        self::$webPush = new WebPush($auth);
        self::$webPush->setDefaultOptions(['TTL' => 2419200]);

        return self::$webPush;
    }

    private static function buildPayload(string $title, string $body, string $icon, string $url, ?int $notificationId = null): string
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'badge' => $icon,
            'data' => [
                'url' => $url,
            ],
        ];

        if ($notificationId !== null) {
            $payload['data']['notification_id'] = $notificationId;
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
