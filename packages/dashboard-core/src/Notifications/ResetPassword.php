<?php

namespace Nreljed\DashboardCore\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Nreljed\DashboardCore\Services\SettingsService;

class ResetPassword extends BaseResetPassword
{
    #[\Override]
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);

        $logoUrl = SettingsService::get('app_logo', '');
        $siteName = SettingsService::get('app_name', config('app.name'));
        $siteUrl = SettingsService::get('app_url', url('/'));

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $body = view('email.templates.password-reset', [
            'userName' => $notifiable->name ?? 'there',
            'resetUrl' => $resetUrl,
            'siteName' => $siteName,
            'siteUrl' => $siteUrl,
            'expireMinutes' => $expireMinutes,
        ])->render();

        return (new MailMessage)
            ->subject("[$siteName] Reset Your Password")
            ->view('email.layouts.email', [
                'logoUrl' => $logoUrl,
                'siteName' => $siteName,
                'siteUrl' => $siteUrl,
                'year' => date('Y'),
                'body' => $body,
                'subject' => "[$siteName] Reset Your Password",
            ]);
    }
}
