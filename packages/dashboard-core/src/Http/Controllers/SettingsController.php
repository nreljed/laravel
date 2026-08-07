<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Requests\ToggleDatabaseMaintenanceRequest;
use Nreljed\DashboardCore\Http\Requests\UpdateSettingsRequest;
use Nreljed\DashboardCore\Http\Requests\UploadFaviconRequest;
use Nreljed\DashboardCore\Http\Requests\UploadLogoRequest;
use Nreljed\DashboardCore\Models\IpAccessRule;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\AuditService;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;
use Nreljed\DashboardCore\Services\SettingsService;

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
        return $this->renderPage('dashboard/admin/settings/notifications', [
            'settings' => Schema::hasTable('settings') ? SettingsService::getGroup('notifications') : new \stdClass,
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
        return $this->renderPage('dashboard/admin/settings/email');
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

        $file = $request->file('logo');

        $filename = $file->store('logos');

        $oldLogo = SettingsService::get('app_logo', '');
        if ($oldLogo) {
            $oldPath = str_replace(Storage::disk('logos')->url(''), '', $oldLogo);
            if ($oldPath && Storage::disk('logos')->exists($oldPath)) {
                Storage::disk('logos')->delete($oldPath);
            }
        }

        SettingsService::set('app_logo', Storage::disk('logos')->url($filename));
        SettingsService::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully!',
            'url' => Storage::disk('logos')->url($filename).'?v='.time(),
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $this->authorize('manage-settings');

        $oldLogo = SettingsService::get('app_logo', '');
        if ($oldLogo) {
            $oldPath = str_replace(Storage::disk('logos')->url(''), '', $oldLogo);
            if ($oldPath && Storage::disk('logos')->exists($oldPath)) {
                Storage::disk('logos')->delete($oldPath);
            }
        }

        SettingsService::set('app_logo', '');
        SettingsService::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Logo removed successfully!',
        ]);
    }

    public function uploadFavicon(UploadFaviconRequest $request): JsonResponse
    {
        $this->authorize('manage-settings');

        $file = $request->file('favicon');

        $filename = $file->store('favicons');

        $oldFavicon = SettingsService::get('app_favicon', '');
        if ($oldFavicon) {
            $oldPath = str_replace(Storage::disk('favicons')->url(''), '', $oldFavicon);
            if ($oldPath && Storage::disk('favicons')->exists($oldPath)) {
                Storage::disk('favicons')->delete($oldPath);
            }
        }

        SettingsService::set('app_favicon', Storage::disk('favicons')->url($filename));
        SettingsService::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Favicon uploaded successfully!',
            'url' => Storage::disk('favicons')->url($filename).'?v='.time(),
        ]);
    }

    public function deleteFavicon(): JsonResponse
    {
        $this->authorize('manage-settings');

        $oldFavicon = SettingsService::get('app_favicon', '');
        if ($oldFavicon) {
            $oldPath = str_replace(Storage::disk('favicons')->url(''), '', $oldFavicon);
            if ($oldPath && Storage::disk('favicons')->exists($oldPath)) {
                Storage::disk('favicons')->delete($oldPath);
            }
        }

        SettingsService::set('app_favicon', '');
        SettingsService::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Favicon removed successfully!',
        ]);
    }
}
