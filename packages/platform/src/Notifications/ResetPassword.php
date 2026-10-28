<?php

namespace Nreljed\Platform\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Nreljed\Platform\Services\EmailTemplateService;

class ResetPassword extends BaseResetPassword
{
    #[\Override]
    public function toMail($notifiable): MailMessage
    {
        $templates = app(EmailTemplateService::class);

        $subject = '['.config('app.name').'] Reset Your Password';

        $body = $templates->render('password-reset', [
            'userName' => $notifiable->name ?? 'there',
            'resetUrl' => $this->resetUrl($notifiable),
            'expireMinutes' => $this->expireMinutes(),
        ]);

        return (new MailMessage)
            ->subject($subject)
            ->view($templates->layoutView(), $templates->globalData([
                'body' => $body,
                'subject' => $subject,
            ]));
    }

    protected function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
    }
}
