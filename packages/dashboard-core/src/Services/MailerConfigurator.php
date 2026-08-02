<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Config;

class MailerConfigurator
{
    public function __construct(protected EmailSettingsService $settingsService) {}

    /**
     * Dynamically configure Laravel mail config from stored settings.
     * Called on every request via AppServiceProvider::boot().
     */
    public function configure(): void
    {
        $settings = $this->settingsService->getOptions();
        $mailer = $settings['mailer'];

        // No SMTP configured via admin UI â†’ keep .env default (no override)
        if ($mailer === 'smtp' && empty($settings['smtp_host'])) {
            return;
        }

        if ($mailer === 'smtp') {
            $config = [
                'transport' => 'smtp',
                'host' => $settings['smtp_host'],
                'port' => (int) $settings['smtp_port'],
                'encryption' => $settings['smtp_encryption'] !== 'none' ? $settings['smtp_encryption'] : null,
                'username' => $settings['smtp_username'],
                'password' => $settings['smtp_password'],
                'timeout' => null,
                'local_domain' => ! empty($settings['smtp_ehlo_domain']) ? $settings['smtp_ehlo_domain'] : parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
                'auth_mode' => $settings['smtp_auth'] ? 'login' : null,
            ];

            if (isset($settings['auto_tls'])) {
                $config['auto_tls'] = (bool) $settings['auto_tls'];
            }

            if (! empty($settings['smtp_allow_self_signed'])) {
                $config['stream'] = [
                    'ssl' => ['allow_self_signed' => true, 'verify_peer' => false],
                ];
                $config['verify_peer'] = false;
            }

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp', $config);
        } elseif (in_array($mailer, ['sendmail', 'log', 'mail', 'ses', 'postmark', 'resend', 'array'], true)) {
            Config::set('mail.default', $mailer);
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
