<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\View;

class EmailTemplateService
{
    protected function injectGlobalData(array $data): array
    {
        $logoUrl = SettingsService::get('app_logo', '');
        $dbName = SettingsService::get('app_name', '');
        $dbUrl = SettingsService::get('app_url', '');

        $data['siteName'] = $dbName ?: config('app.name');
        $data['siteUrl'] = $dbUrl ?: url('/');
        $data['logoUrl'] = $logoUrl;
        $data['year'] = date('Y');

        return $data;
    }

    public function render(string $template, array $data = []): string
    {
        $viewName = 'email.templates.'.$template;

        if (! View::exists($viewName)) {
            $viewName = 'email.templates.default';
        }

        $data = $this->injectGlobalData($data);

        return View::make($viewName, $data)->render();
    }

    public function renderWithLayout(string $template, array $data = []): string
    {
        $layoutView = 'email.layouts.email';
        $data = $this->injectGlobalData($data);

        $templateView = 'email.templates.'.$template;
        if (! View::exists($templateView)) {
            $templateView = 'email.templates.default';
        }

        $data['body'] = View::make($templateView, $data)->render();

        return View::make($layoutView, $data)->render();
    }

    public function renderPreview(string $template, array $data = []): string
    {
        $layoutView = 'email.layouts.preview';
        $data = $this->injectGlobalData($data);

        $templateView = 'email.templates.'.$template;
        if (! View::exists($templateView)) {
            $templateView = 'email.templates.default';
        }

        $data['body'] = View::make($templateView, $data)->render();

        return View::make($layoutView, $data)->render();
    }

    public function exists(string $template): bool
    {
        return View::exists('email.templates.'.$template);
    }
}
