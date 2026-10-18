<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Requests\Email\SendTestEmailRequest;
use Nreljed\Platform\Http\Requests\Email\UpdateEmailSettingsRequest;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Services\EmailSettingsService;
use Nreljed\Platform\Services\EmailTemplateService;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Services\MailService;
use Nreljed\Platform\Services\NotificationTypeRegistry;

class EmailSettingsController extends Controller
{
    public function __construct(protected EmailSettingsService $settingsService, protected MailService $mailService, protected EmailTemplateService $templateService, protected FeatureRegistry $featureRegistry) {}

    /**
     * Update SMTP/email settings.
     */
    public function update(UpdateEmailSettingsRequest $request)
    {
        $this->settingsService->updateFromRequest($request->validated());

        return redirect()->back()->with('status', 'Email settings saved successfully!');
    }

    /**
     * Send a simple test email to verify SMTP configuration.
     */
    public function sendTest(Request $request)
    {
        $to = $request->input('to', '');
        if (! $to) {
            $to = $request->user()->email;
        }

        $settings = $this->settingsService->forFrontend();
        $subject = '['.config('app.name').'] Test Email';

        try {
            $result = $this->mailService->sendNow(
                $to,
                $subject,
                'test',
                [
                    'recipientName' => $request->user()->name,
                    'recipientEmail' => $to,
                ]
            );

            $debug = [
                'success' => $result,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host', 'N/A'),
                'port' => config('mail.mailers.smtp.port', 'N/A'),
                'encryption' => config('mail.mailers.smtp.encryption', 'N/A'),
                'username' => config('mail.mailers.smtp.username', 'N/A'),
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => $to,
                'subject' => $subject,
                'template' => 'test',
                'error' => null,
            ];

            if ($result) {
                return redirect()->back()->with('mail_debug', $debug)->with('status', 'Test email sent successfully to '.$to);
            }

            $debug['error'] = 'Mail::html() returned false without exception';

            return redirect()->back()->with('mail_debug', $debug)->withErrors(['error' => 'Failed to send test email.']);
        } catch (\Throwable $e) {
            $debug = [
                'success' => false,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host', 'N/A'),
                'port' => config('mail.mailers.smtp.port', 'N/A'),
                'encryption' => config('mail.mailers.smtp.encryption', 'N/A'),
                'username' => config('mail.mailers.smtp.username', 'N/A'),
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => $to,
                'subject' => $subject,
                'template' => 'test',
                'error' => $e->getMessage(),
            ];

            return redirect()->back()->with('mail_debug', $debug)->withErrors(['error' => 'Failed to send test email: '.$e->getMessage()]);
        }
    }

    /**
     * Send a template test email for preview.
     */
    public function sendTemplateTest(SendTestEmailRequest $request)
    {
        $to = $request->validated('to') ?: $request->user()->email;
        $template = $request->validated('template');

        if (! $this->templateService->exists($template)) {
            return redirect()->back()->withErrors(['error' => 'Template not found: '.$template]);
        }

        $sampleData = $this->getSampleDataForTemplate($template);
        $subject = '['.config('app.name').'] Preview: '.($sampleData['subject'] ?? $template);

        try {
            $result = $this->mailService->sendNow(
                $to,
                $subject,
                $template,
                $sampleData
            );

            $debug = [
                'success' => $result,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host', 'N/A'),
                'port' => config('mail.mailers.smtp.port', 'N/A'),
                'encryption' => config('mail.mailers.smtp.encryption', 'N/A'),
                'username' => config('mail.mailers.smtp.username', 'N/A'),
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'error' => null,
            ];

            if ($result) {
                return redirect()->back()->with('mail_debug', $debug)->with('status', '"'.$template.'" template sent successfully to '.$to);
            }

            $debug['error'] = 'Mail::html() returned false without exception';

            return redirect()->back()->with('mail_debug', $debug)->withErrors(['error' => 'Failed to send template email.']);
        } catch (\Throwable $e) {
            $debug = [
                'success' => false,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host', 'N/A'),
                'port' => config('mail.mailers.smtp.port', 'N/A'),
                'encryption' => config('mail.mailers.smtp.encryption', 'N/A'),
                'username' => config('mail.mailers.smtp.username', 'N/A'),
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'error' => $e->getMessage(),
            ];

            return redirect()->back()->with('mail_debug', $debug)->withErrors(['error' => 'Failed to send template email: '.$e->getMessage()]);
        }
    }

    /**
     * Render a template preview (HTML) for the simulation UI.
     */
    public function previewTemplate(Request $request, string $template)
    {
        if (! $this->templateService->exists($template)) {
            return redirect()->back()->withErrors(['error' => 'Template not found: '.$template]);
        }

        $sampleData = $this->getSampleDataForTemplate($template);

        // Fragment preview (for dialog) — no HTML/BODY wrapper
        if ($request->query('fragment') === '1') {
            $html = $this->templateService->renderPreview($template, $sampleData);

            return response($html, 200)->header('Content-Type', 'text/html');
        }

        $html = $this->templateService->renderWithLayout($template, $sampleData);

        if ($request->inertia()) {
            return inertia()->render('dashboard/admin/settings/email', [
                'previewHtml' => $html,
            ]);
        }

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * Get sample data for a template by looking it up in getTemplates().
     */
    protected function getSampleDataForTemplate(string $template): array
    {
        $allTemplates = $this->getTemplates();

        return $allTemplates[$template]['sample_data'] ?? [];
    }

    /**
     * Get all available email templates (core + modules) with sample data.
     */
    public function getTemplates(): array
    {
        $templates = $this->getCoreTemplates();

        $featureTemplates = $this->featureRegistry->getAllEmailTemplates();
        if (! empty($featureTemplates)) {
            $templates = array_merge($templates, $featureTemplates);
        }

        return $templates;
    }

    /**
     * Join every registered notification type with its email template so the
     * settings UI can show which user action triggers which email, and which
     * templates are shipped but not yet wired to a notification type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEmailCatalog(): array
    {
        $templates = $this->getTemplates();
        $types = app(NotificationTypeRegistry::class)->all();

        $catalog = [];

        foreach ($types as $typeKey => $type) {
            $templateKey = $type['email_template'] ?? null;

            $catalog[] = [
                'type_key' => $typeKey,
                'label' => $type['label'] ?? $typeKey,
                'description' => $type['description'] ?? '',
                'group' => $type['group'] ?? 'Other',
                'template_key' => $templateKey,
                'template_label' => $templateKey !== null ? ($templates[$templateKey]['label'] ?? $templateKey) : null,
                'has_template' => $templateKey !== null && $this->templateService->exists($templateKey),
                'default_channels' => $type['default_channels'] ?? [],
                'default_enabled' => (bool) ($type['default_enabled'] ?? true),
                'required' => (bool) ($type['required'] ?? false),
                'severity' => $type['severity'] ?? 'info',
            ];
        }

        $wiredTemplates = array_filter(array_column($catalog, 'template_key'));

        foreach ($templates as $templateKey => $template) {
            if (in_array($templateKey, $wiredTemplates, true)) {
                continue;
            }

            $catalog[] = [
                'type_key' => null,
                'label' => $template['label'] ?? $templateKey,
                'description' => 'Template is available but not wired to a notification type.',
                'group' => $template['group'] ?? 'Other',
                'template_key' => $templateKey,
                'template_label' => $template['label'] ?? $templateKey,
                'has_template' => true,
                'default_channels' => [],
                'default_enabled' => true,
                'required' => false,
                'severity' => 'info',
            ];
        }

        usort($catalog, fn (array $a, array $b) => [$a['group'], $a['label']] <=> [$b['group'], $b['label']]);

        return $catalog;
    }

    protected function getCoreTemplates(): array
    {
        $user = request()->user();

        return [
            'test' => [
                'label' => 'Simple Test Email (SMTP Check)',
                'group' => 'System',
                'sample_data' => [
                    'recipientName' => $this->recipientName($user),
                    'recipientEmail' => $this->recipientEmail($user),
                    'siteName' => config('app.name'),
                ],
            ],
            'default' => [
                'label' => 'Default Fallback',
                'group' => 'System',
                'sample_data' => [
                    'title' => 'Notification',
                    'message' => 'This is a sample notification message.',
                    'siteName' => config('app.name'),
                ],
            ],
            'new-user' => [
                'label' => 'New User Welcome',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'userEmail' => $this->recipientEmail($user),
                    'loginUrl' => route('login'),
                    'siteName' => config('app.name'),
                ],
            ],
            'new-user-admin' => [
                'label' => 'New User (Admin Notification)',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'userLogin' => $this->recipientName($user, 'johndoe'),
                    'userEmail' => $this->recipientEmail($user),
                    'profileUrl' => route('dashboard.admin.users.index'),
                    'siteName' => config('app.name'),
                ],
            ],
            'password-changed' => [
                'label' => 'Password Changed',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'resetUrl' => route('password.request'),
                    'siteName' => config('app.name'),
                ],
            ],
            'password-reset' => [
                'label' => 'Password Reset Request',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'resetUrl' => route('password.request'),
                    'expireMinutes' => 60,
                    'siteName' => config('app.name'),
                ],
            ],
            'verify-email' => [
                'label' => 'Email Verification',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'verificationUrl' => route('verification.notice'),
                    'siteName' => config('app.name'),
                ],
            ],
            'email-changed' => [
                'label' => 'Email Changed',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'oldEmail' => 'old@example.com',
                    'newEmail' => 'new@example.com',
                    'siteName' => config('app.name'),
                ],
            ],
            'account-deleted' => [
                'label' => 'Account Deleted',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'siteName' => config('app.name'),
                ],
            ],
            'welcome' => [
                'label' => 'Account Welcome',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $this->recipientName($user),
                    'setPasswordUrl' => route('password.request'),
                    'loginUrl' => route('login'),
                    'expireMinutes' => 60,
                    'siteName' => config('app.name'),
                ],
            ],
            'contact-received' => [
                'label' => 'Contact Form Message',
                'group' => 'Contact',
                'sample_data' => [
                    'senderName' => 'Jane Visitor',
                    'senderEmail' => 'jane@example.com',
                    'subjectLine' => 'Question about pricing',
                    'messageBody' => "Hi,\n\nCould you tell me more about your pricing plans?\n\nThanks!",
                    'submittedAt' => now()->toDateTimeString(),
                    'contactUrl' => route('dashboard.admin.contact.index'),
                    'siteName' => config('app.name'),
                ],
            ],
        ];
    }

    protected function recipientName(?User $user, string $fallback = 'User'): string
    {
        return $user->name ?? $fallback;
    }

    protected function recipientEmail(?User $user): string
    {
        return $user->email ?? 'user@example.com';
    }
}
