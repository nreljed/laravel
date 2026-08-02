<?php

namespace Nreljed\DashboardCore\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Nreljed\DashboardCore\Services\SettingsService;

class VerifyEmail extends BaseVerifyEmail
{
    public function __construct(
        private readonly ?string $preGeneratedUrl = null,
    ) {
        //
    }

    #[\Override]
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->preGeneratedUrl ?? $this->verificationUrl($notifiable);

        $logoUrl = SettingsService::get('app_logo', '');
        $siteName = SettingsService::get('app_name', config('app.name'));
        $siteUrl = SettingsService::get('app_url', url('/'));

        $body = view('email.partials.verify-email-body', [
            'userName' => $notifiable->name ?? 'there',
            'verificationUrl' => $verificationUrl,
            'siteName' => $siteName,
        ])->render();

        return (new MailMessage)
            ->subject("[$siteName] Verify Your Email Address")
            ->view('email.layouts.email', [
                'logoUrl' => $logoUrl,
                'siteName' => $siteName,
                'siteUrl' => $siteUrl,
                'year' => date('Y'),
                'body' => $body,
                'subject' => "[$siteName] Verify Your Email Address",
            ]);
    }
}
