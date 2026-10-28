<?php

namespace Nreljed\Platform\Services;

use Illuminate\Support\Facades\Config;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailerConfigurator
{
    public function __construct(protected EmailSettingsService $settingsService) {}

    /**
     * Dynamically configure Laravel mail config from stored settings.
     * Called on every request via the platform service provider.
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

    /**
     * Apply per-message envelope settings (From, Reply-To, Return-Path) on
     * every outgoing message. Mirrors the PHPMailer behaviour in Project-WP:
     * force-from only overrides when enabled, and the return-path prefers the
     * "use from as return-path" checkbox over a manually entered address.
     */
    public function applyMessageSettings(Email $message): void
    {
        $settings = $this->settingsService->getOptions();

        if ($settings['force_from_email'] && $settings['from_email']) {
            $message->from(new Address($settings['from_email'], $settings['from_name'] ?: ''));
        } elseif ($settings['force_from_name'] && $settings['from_name'] && $settings['from_email']) {
            $message->from(new Address($settings['from_email'], $settings['from_name']));
        }

        if ($settings['reply_to_email'] && ! $message->getReplyTo()) {
            $message->replyTo(new Address($settings['reply_to_email'], $settings['from_name'] ?: ''));
        }

        if ($settings['use_from_as_return_path'] && $settings['from_email']) {
            $message->returnPath($settings['from_email']);
        } elseif ($settings['return_path']) {
            $message->returnPath($settings['return_path']);
        }
    }
}
