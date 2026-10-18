<?php

namespace Nreljed\Platform\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Nreljed\Platform\Services\EmailTemplateService;

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
        $templates = app(EmailTemplateService::class);

        $verificationUrl = $this->preGeneratedUrl ?? $this->verificationUrl($notifiable);
        $subject = '['.config('app.name').'] Verify Your Email Address';

        $body = $templates->render('verify-email', [
            'userName' => $notifiable->name ?? 'there',
            'verificationUrl' => $verificationUrl,
        ]);

        return (new MailMessage)
            ->subject($subject)
            ->view($templates->layoutView(), $templates->globalData([
                'body' => $body,
                'subject' => $subject,
            ]));
    }
}
