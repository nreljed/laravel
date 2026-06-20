<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class MailerConfigurator
{
    protected EmailSettingsService $settingsService;

    public function __construct(EmailSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Dynamically configure Laravel mail config from stored settings.
     * Called on every request via AppServiceProvider::boot().
     */
    public function configure(): void
    {
        $settings = $this->settingsService->getOptions();

        if ($settings['mailer'] === 'smtp') {
            $config = [
                'transport' => 'smtp',
                'host' => $settings['smtp_host'],
                'port' => (int) $settings['smtp_port'],
                'encryption' => $settings['smtp_encryption'] !== 'none' ? $settings['smtp_encryption'] : null,
                'username' => $settings['smtp_username'],
                'password' => $settings['smtp_password'],
                'timeout' => null,
                'auth_mode' => $settings['smtp_auth'] ? 'login' : null,
            ];

            if (isset($settings['auto_tls'])) {
                $config['stream'] = [
                    'ssl' => ['allow_self_signed' => true, 'verify_peer' => false],
                ];
            }

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp', $config);
        }

        if ($settings['from_email']) {
            Config::set('mail.from.address', $settings['from_email']);
        }
        if ($settings['from_name']) {
            Config::set('mail.from.name', $settings['from_name']);
        }

        if ($settings['reply_to_email']) {
            Config::set('mail.reply_to', [
                'address' => $settings['reply_to_email'],
                'name' => $settings['from_name'] ?? null,
            ]);
        }
    }
}
