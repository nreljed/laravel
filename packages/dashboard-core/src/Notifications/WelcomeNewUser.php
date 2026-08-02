<?php

namespace Nreljed\DashboardCore\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Nreljed\DashboardCore\Services\SettingsService;

class WelcomeNewUser extends Notification
{
    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $setPasswordUrl = $this->resetUrl($notifiable);

        $logoUrl = SettingsService::get('app_logo', '');
        $siteName = SettingsService::get('app_name', config('app.name'));
        $siteUrl = SettingsService::get('app_url', url('/'));

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $body = view('email.templates.welcome', [
            'userName' => $notifiable->name ?? 'there',
            'setPasswordUrl' => $setPasswordUrl,
            'siteName' => $siteName,
            'siteUrl' => $siteUrl,
            'expireMinutes' => $expireMinutes,
        ])->render();

        return (new MailMessage)
            ->subject("Welcome to $siteName!")
            ->view('email.layouts.email', [
                'logoUrl' => $logoUrl,
                'siteName' => $siteName,
                'siteUrl' => $siteUrl,
                'year' => date('Y'),
                'body' => $body,
                'subject' => "Welcome to $siteName!",
            ]);
    }

    protected function resetUrl($notifiable): string
    {
        return URL::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
