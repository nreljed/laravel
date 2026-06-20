<?php

namespace App\Http\Controllers;

use App\Services\EmailSettingsService;
use App\Services\EmailTemplateService;
use App\Services\MailService;
use App\Modules\ModuleRegistry;
use Illuminate\Http\Request;

class EmailSettingsController extends Controller
{
    protected EmailSettingsService $settingsService;
    protected MailService $mailService;
    protected EmailTemplateService $templateService;
    protected ModuleRegistry $moduleRegistry;

    public function __construct(
        EmailSettingsService $settingsService,
        MailService $mailService,
        EmailTemplateService $templateService,
        ModuleRegistry $moduleRegistry
    ) {
        $this->settingsService = $settingsService;
        $this->mailService = $mailService;
        $this->templateService = $templateService;
        $this->moduleRegistry = $moduleRegistry;
    }

    /**
     * Update SMTP/email settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mailer' => 'nullable|string|in:smtp',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:tls,ssl,none',
            'smtp_auth' => 'nullable|boolean',
            'auto_tls' => 'nullable|boolean',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'reply_to_email' => 'nullable|email|max:255',
            'return_path' => 'nullable|email|max:255',
            'use_from_as_return_path' => 'nullable|boolean',
            'force_from_email' => 'nullable|boolean',
            'force_from_name' => 'nullable|boolean',
        ]);

        $this->settingsService->updateFromRequest($validated);

        return redirect()->back()->with('status', 'Email settings saved successfully!');
    }

    /**
     * Send a simple test email to verify SMTP configuration.
     */
    public function sendTest(Request $request)
    {
        $to = $request->input('to', '');
        if (!$to) {
            $to = $request->user()->email;
        }

        $result = $this->mailService->send(
            $to,
            '[' . config('app.name') . '] Test Email',
            'test',
            [
                'recipientName' => $request->user()->name,
                'recipientEmail' => $to,
            ]
        );

        if ($result) {
            return redirect()->back()->with('status', 'Test email sent successfully to ' . $to);
        }

        return redirect()->back()->withErrors(['error' => 'Failed to send test email. Please check your SMTP settings and try again.']);
    }

    /**
     * Send a template test email for preview.
     */
    public function sendTemplateTest(Request $request)
    {
        $validated = $request->validate([
            'template' => 'required|string',
            'to' => 'nullable|email',
        ]);

        $to = $validated['to'] ?: $request->user()->email;
        $template = $validated['template'];

        if (!$this->templateService->exists($template)) {
            return redirect()->back()->withErrors(['error' => 'Template not found: ' . $template]);
        }

        $sampleData = $this->getSampleDataForTemplate($template);
        $subject = '[' . config('app.name') . '] Preview: ' . ($sampleData['subject'] ?? $template);

        $result = $this->mailService->send(
            $to,
            $subject,
            $template,
            $sampleData
        );

        if ($result) {
            return redirect()->back()->with('status', '"' . $template . '" template sent successfully to ' . $to);
        }

        return redirect()->back()->withErrors(['error' => 'Failed to send template email.']);
    }

    /**
     * Render a template preview (HTML) for the simulation UI.
     */
    public function previewTemplate(Request $request, string $template)
    {
        if (!$this->templateService->exists($template)) {
            return redirect()->back()->withErrors(['error' => 'Template not found: ' . $template]);
        }

        $sampleData = $this->getSampleDataForTemplate($template);
        $html = $this->templateService->renderWithLayout($template, $sampleData);

        if ($request->inertia()) {
            return inertia()->render('Admin/Settings', [
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

        foreach ($this->moduleRegistry->getBooted() as $module) {
            $moduleTemplates = $module->getEmailTemplates();
            if (!empty($moduleTemplates)) {
                $templates = array_merge($templates, $moduleTemplates);
            }
        }

        return $templates;
    }

    protected function getCoreTemplates(): array
    {
        $user = request()->user();

        return [
            'test' => [
                'label' => 'Simple Test Email (SMTP Check)',
                'group' => 'System',
                'sample_data' => [
                    'recipientName' => $user?->name ?? 'User',
                    'recipientEmail' => $user?->email ?? 'user@example.com',
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
                    'userName' => $user?->name ?? 'User',
                    'userEmail' => $user?->email ?? 'user@example.com',
                    'loginUrl' => route('login'),
                    'siteName' => config('app.name'),
                ],
            ],
            'new-user-admin' => [
                'label' => 'New User (Admin Notification)',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $user?->name ?? 'User',
                    'userEmail' => $user?->email ?? 'user@example.com',
                    'siteName' => config('app.name'),
                ],
            ],
            'password-changed' => [
                'label' => 'Password Changed',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $user?->name ?? 'User',
                    'resetUrl' => route('password.request'),
                    'siteName' => config('app.name'),
                ],
            ],
            'password-reset' => [
                'label' => 'Password Reset Request',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $user?->name ?? 'User',
                    'resetUrl' => route('password.request'),
                    'expiry' => '24 hours',
                    'siteName' => config('app.name'),
                ],
            ],
            'email-changed' => [
                'label' => 'Email Changed',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $user?->name ?? 'User',
                    'oldEmail' => 'old@example.com',
                    'newEmail' => 'new@example.com',
                    'siteName' => config('app.name'),
                ],
            ],
            'account-deleted' => [
                'label' => 'Account Deleted',
                'group' => 'Account',
                'sample_data' => [
                    'userName' => $user?->name ?? 'User',
                    'siteName' => config('app.name'),
                ],
            ],
            'property-inquiry' => [
                'label' => 'Property Inquiry',
                'group' => 'Properties',
                'sample_data' => [
                    'senderName' => $user?->name ?? 'Guest',
                    'senderEmail' => $user?->email ?? 'guest@example.com',
                    'senderMessage' => 'I am interested in this property. Please contact me with more details.',
                    'propertyTitle' => 'Sample Property',
                    'propertyUrl' => route('dashboard'),
                    'siteName' => config('app.name'),
                ],
            ],
        ];
    }
}
