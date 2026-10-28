<?php

namespace Nreljed\Platform\Services;

use Illuminate\Support\Facades\View;

class EmailTemplateService
{
    /**
     * template key => owning feature blade namespace.
     *
     * Built lazily from FeatureRegistry::getAllEmailTemplates() so that any
     * feature module (Shop, Blog, ...) can own a template simply by declaring
     * a key in getEmailTemplates() and shipping a
     * resources/views/email/templates/{key}.blade.php namespaced by its slug.
     *
     * @var array<string,string>|null
     */
    protected ?array $owners = null;

    public function __construct(protected FeatureRegistry $featureRegistry) {}

    /**
     * Map each registered email template key to the owning feature's blade
     * namespace. A key is owned by a feature only when that feature also ships
     * the corresponding blade under its namespace; otherwise it is ignored so
     * resolution falls through to the core default.
     *
     * @return array<string,string>
     */
    protected function owners(): array
    {
        if ($this->owners !== null) {
            return $this->owners;
        }

        $owners = [];

        try {
            foreach ($this->featureRegistry->allProviders() as $provider) {
                $namespace = strtolower($provider->slug());

                foreach (array_keys($provider->getEmailTemplates()) as $key) {
                    if (View::exists($namespace.'::email/templates/'.$key)) {
                        $owners[$key] = $namespace;
                    }
                }
            }
        } catch (\Throwable) {
            // Fail closed to core resolution when the registry isn't available.
            $owners = [];
        }

        return $this->owners = $owners;
    }

    /**
     * Resolve the view name for a template key with the following precedence
     * (highest wins):
     *
     *   1. project override  - resources/views/email/templates/{key}
     *   2. owning feature    - {feature}::email/templates/{key}
     *   3. core fallback     - core::email/templates/{key}
     *   4. core default      - core::email/templates/default
     */
    protected function resolveViewName(string $template): string
    {
        $projectView = 'email.templates.'.$template;
        if (View::exists($projectView)) {
            return $projectView;
        }

        $owner = $this->owners()[$template] ?? null;
        if ($owner !== null && View::exists($owner.'::email.templates.'.$template)) {
            return $owner.'::email.templates.'.$template;
        }

        if (View::exists('core::email.templates.'.$template)) {
            return 'core::email.templates.'.$template;
        }

        return 'core::email.templates.default';
    }

    /**
     * Public accessor for the resolved layout view name. Notifications use it
     * to hand a fully-resolved layout to MailMessage::view() so a project can
     * override email.layouts.email without forking the notification.
     */
    public function layoutView(string $kind = 'email'): string
    {
        return $this->resolveLayoutView($kind);
    }

    /**
     * Public accessor for the global data merge (siteName/siteUrl/logoUrl/year/
     * managePreferencesUrl). Notifications pass their layout payload through it
     * so every email gets the same globals the template service injects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function globalData(array $data = []): array
    {
        return $this->injectGlobalData($data);
    }

    protected function resolveLayoutView(string $kind): string
    {
        // 'email' => shared layout; 'preview' => simulation layout. Prefer a
        // project override, then the core layout.
        $project = 'email.layouts.'.$kind;
        if (View::exists($project)) {
            return $project;
        }

        return 'core::email.layouts.'.$kind;
    }

    protected function injectGlobalData(array $data): array
    {
        $logoUrl = SettingsService::get('app_logo', '');
        $dbName = SettingsService::get('app_name', '');
        $dbUrl = SettingsService::get('app_url', '');

        $data['siteName'] = $dbName ?: config('app.name');
        $data['siteUrl'] = $dbUrl ?: url('/');
        $data['logoUrl'] = $logoUrl;
        $data['year'] = date('Y');

        if (empty($data['managePreferencesUrl'])) {
            $data['managePreferencesUrl'] = route('dashboard.account.notifications', [], false);
        }

        return $data;
    }

    public function render(string $template, array $data = []): string
    {
        $viewName = $this->resolveViewName($template);
        $data = $this->injectGlobalData($data);

        return View::make($viewName, $data)->render();
    }

    public function renderWithLayout(string $template, array $data = []): string
    {
        $layoutView = $this->resolveLayoutView('email');
        $data = $this->injectGlobalData($data);

        $templateView = $this->resolveViewName($template);
        $data['body'] = View::make($templateView, $data)->render();

        return View::make($layoutView, $data)->render();
    }

    public function renderPreview(string $template, array $data = []): string
    {
        $layoutView = $this->resolveLayoutView('preview');
        $data = $this->injectGlobalData($data);

        $templateView = $this->resolveViewName($template);
        $data['body'] = View::make($templateView, $data)->render();

        return View::make($layoutView, $data)->render();
    }

    public function exists(string $template): bool
    {
        return $this->resolveViewName($template) !== 'core::email.templates.default'
            || View::exists('core::email.templates.'.$template);
    }
}
