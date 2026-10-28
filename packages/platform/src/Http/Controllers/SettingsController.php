<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Requests\ToggleDatabaseMaintenanceRequest;
use Nreljed\Platform\Http\Requests\UpdateSettingsRequest;
use Nreljed\Platform\Http\Requests\UploadFaviconRequest;
use Nreljed\Platform\Http\Requests\UploadLogoRequest;
use Nreljed\Platform\Models\IpAccessRule;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\AuditService;
use Nreljed\Platform\Services\DatabaseMaintenanceService;
use Nreljed\Platform\Services\EmailSettingsService;
use Nreljed\Platform\Services\NotificationTypeRegistry;
use Nreljed\Platform\Services\SettingsService;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard.admin.settings.general');
    }

    public function general(): Response
    {
        return $this->renderPage('dashboard/admin/settings/general', [
            'settings' => Schema::hasTable('settings')
                ? array_merge(
                    SettingsService::getGroup('general'),
                    SettingsService::getGroup('formatting'),
                )
                : new \stdClass,
        ]);
    }

    public function registration(): Response
    {
        $roles = Schema::hasTable('roles')
            ? Role::where('slug', '!=', 'admin')->select('id', 'name', 'slug')->orderBy('sort_order')->get()
            : collect();

        $settings = Schema::hasTable('settings')
            ? SettingsService::getGroup('registration')
            : [];

        $firstAvailableRole = $roles->first()?->slug;

        $defaultRoleSlug = $settings['default_role'] ?? null;
        if (! $defaultRoleSlug || ! $roles->contains('slug', $defaultRoleSlug)) {
            // Display-only fallback — persisted when registration settings are saved.
            $settings['default_role'] = $firstAvailableRole;
        }

        return $this->renderPage('dashboard/admin/settings/registration', [
            'settings' => $settings,
            'roles' => $roles,
        ]);
    }

    public function notifications(): Response
    {
        try {
            $notificationTypes = app(NotificationTypeRegistry::class)->groups();
        } catch (\Exception) {
            $notificationTypes = [];
        }

        return $this->renderPage('dashboard/admin/settings/notifications', [
            'settings' => Schema::hasTable('settings') ? SettingsService::getGroup('notifications') : new \stdClass,
            'notificationTableExists' => Schema::hasTable('notifications'),
            'notificationTypes' => $notificationTypes,
        ]);
    }

    public function security(): Response
    {
        return $this->renderPage('dashboard/admin/settings/security', [
            'settings' => Schema::hasTable('settings') ? SettingsService::getGroup('security') : new \stdClass,
        ]);
    }

    public function email(): Response
    {
        $emailSettings = [];
        $emailTemplates = [];
        $emailCatalog = [];

        try {
            $emailSettings = app(EmailSettingsService::class)->forFrontend();
        } catch (\Exception) {
        }

        try {
            $emailController = app(EmailSettingsController::class);
            $emailTemplates = $emailController->getTemplates();
            $emailCatalog = $emailController->getEmailCatalog();
        } catch (\Throwable) {
        }

        return $this->renderPage('dashboard/admin/settings/email', [
            'emailSettings' => $emailSettings,
            'emailTemplates' => $emailTemplates,
            'emailCatalog' => $emailCatalog,
        ]);
    }

    public function maintenance(): Response
    {
        return $this->renderPage('dashboard/admin/settings/maintenance', [
            'settings' => Schema::hasTable('settings') ? SettingsService::getGroup('maintenance') : new \stdClass,
        ]);
    }

    public function dev(): Response
    {
        return $this->renderPage('dashboard/admin/settings/dev', [
            'databaseMaintenanceEnabled' => app(DatabaseMaintenanceService::class)->isEnabled(),
            'isProduction' => app()->isProduction(),
        ]);
    }

    public function toggleDatabaseMaintenance(ToggleDatabaseMaintenanceRequest $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');

        SettingsService::set('database_maintenance_enabled', $enabled ? '1' : '0');
        Cache::forget('app_features.database_maintenance_enabled');

        AuditService::log(AuditAction::SettingsChanged, $request->user()->id, [
            'changed_keys' => ['database_maintenance_enabled'],
        ]);

        return back()->with('status', $enabled
            ? 'Database maintenance UI enabled. Accessible from Administration → Database Maintenance.'
            : 'Database maintenance UI disabled.');
    }

    public function ipAccess(): Response
    {
        return $this->renderPage('dashboard/admin/settings/ip-access', [
            'ipRules' => Schema::hasTable('ip_access_rules')
                ? IpAccessRule::orderByDesc('is_active')->orderByDesc('created_at')->get()
                : [],
        ]);
    }

    private function renderPage(string $page, array $extra = []): Response
    {
        return Inertia::render($page, $extra);
    }

    public function edit(): RedirectResponse
    {
        return $this->index();
    }

    public function update(UpdateSettingsRequest $request): Response|RedirectResponse
    {
        $this->authorize('manage-settings');

        if (! Schema::hasTable('settings')) {
            return redirect()->back()->with('status', 'Settings table not yet migrated.');
        }

        $validated = array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $request->validated());

        if (isset($validated['maintenance_mode'])) {
            if ($validated['maintenance_mode'] === '1' && SettingsService::get('maintenance_bypass_token', '') === '') {
                $validated['maintenance_bypass_token'] = Str::random(64);
                RecoveryController::sendRecoveryEmails();
            } elseif ($validated['maintenance_mode'] === '0') {
                $validated['maintenance_bypass_token'] = '';
            }
        }

        SettingsService::setMany($validated);
        SettingsService::clearCache();

        AuditService::log(AuditAction::SettingsChanged, $request->user()->id, [
            'changed_keys' => array_keys($validated),
        ]);

        return redirect()->back()->with('status', 'Settings saved successfully.');
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $this->authorize('manage-settings');

        return response()->json($this->uploadSettingImage($request->file('logo'), 'logos', 'app_logo', 'Logo'));
    }

    public function deleteLogo(): JsonResponse
    {
        $this->authorize('manage-settings');
        $this->deleteSettingImage('logos', 'app_logo');

        return response()->json([
            'success' => true,
            'message' => 'Logo removed successfully!',
        ]);
    }

    public function uploadFavicon(UploadFaviconRequest $request): JsonResponse
    {
        $this->authorize('manage-settings');

        return response()->json($this->uploadSettingImage($request->file('favicon'), 'favicons', 'app_favicon', 'Favicon'));
    }

    public function deleteFavicon(): JsonResponse
    {
        $this->authorize('manage-settings');
        $this->deleteSettingImage('favicons', 'app_favicon');

        return response()->json([
            'success' => true,
            'message' => 'Favicon removed successfully!',
        ]);
    }

    /**
     * @return array{success: true, message: string, url: string}
     */
    private function uploadSettingImage(UploadedFile $file, string $disk, string $settingKey, string $label): array
    {
        $filename = $file->store($disk);

        $this->deleteSettingImage($disk, $settingKey);

        SettingsService::set($settingKey, Storage::disk($disk)->url($filename));
        SettingsService::clearCache();

        return [
            'success' => true,
            'message' => "{$label} uploaded successfully!",
            'url' => Storage::disk($disk)->url($filename).'?v='.time(),
        ];
    }

    private function deleteSettingImage(string $disk, string $settingKey): void
    {
        $oldValue = SettingsService::get($settingKey, '');
        if ($oldValue) {
            $oldPath = str_replace(Storage::disk($disk)->url(''), '', $oldValue);
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->delete($oldPath);
            }
        }

        SettingsService::set($settingKey, '');
        SettingsService::clearCache();
    }
}
