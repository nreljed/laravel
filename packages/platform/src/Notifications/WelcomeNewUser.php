<?php

namespace Nreljed\Platform\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Nreljed\Platform\Services\EmailTemplateService;

/**
 * Welcome email for a newly created account.
 *
 * Dispatched through NotificationService::notifyViaPreferences() under the
 * `user.welcome` type, which resolves the `welcome` email template and sends
 * on the registry's email channel. The optional token supports the
 * admin-created-user flow ("set your password"); self-registration passes no
 * token and the template falls back to a log-in call to action.
 */
class WelcomeNewUser extends Notification
{
    public function __construct(
        public readonly ?string $token = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Payload consumed by NotificationService::sendNotificationEmail() when the
     * welcome email is dispatched through the preference-aware pipeline.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'userName' => $this->displayName($notifiable),
            'setPasswordUrl' => $this->setPasswordUrl($notifiable),
            'loginUrl' => route('login'),
            'expireMinutes' => $this->expireMinutes(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $templates = app(EmailTemplateService::class);

        $subject = 'Welcome to '.config('app.name').'!';

        $body = $templates->render('welcome', array_merge(
            $this->toArray($notifiable),
            ['siteName' => config('app.name')],
        ));

        return (new MailMessage)
            ->subject($subject)
            ->view($templates->layoutView(), $templates->globalData([
                'body' => $body,
                'subject' => $subject,
            ]));
    }

    protected function displayName(object $notifiable): string
    {
        $displayName = $notifiable->display_name ?? null;

        return $displayName ?: ($notifiable->name ?? 'there');
    }

    protected function setPasswordUrl(object $notifiable): ?string
    {
        if ($this->token === null || $this->token === '') {
            return null;
        }

        return URL::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    protected function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
    }
}
