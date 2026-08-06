<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Nreljed\DashboardCore\Ai\Controllers\AiProviderController;
use Nreljed\DashboardCore\Deploy\Controllers\DeployController;
use Nreljed\DashboardCore\Http\Controllers\Admin\AuditLogController;
use Nreljed\DashboardCore\Http\Controllers\Admin\BulkUserController;
use Nreljed\DashboardCore\Http\Controllers\Admin\DatabaseMaintenanceController;
use Nreljed\DashboardCore\Http\Controllers\Admin\ImpersonationController;
use Nreljed\DashboardCore\Http\Controllers\Admin\IpAccessRuleController;
use Nreljed\DashboardCore\Http\Controllers\Admin\PermissionController;
use Nreljed\DashboardCore\Http\Controllers\Admin\RoleController;
use Nreljed\DashboardCore\Http\Controllers\Admin\SessionController;
use Nreljed\DashboardCore\Http\Controllers\Admin\UserController;
use Nreljed\DashboardCore\Http\Controllers\AdminController;
use Nreljed\DashboardCore\Http\Controllers\Api\NotificationPollController;
use Nreljed\DashboardCore\Http\Controllers\AvatarController;
use Nreljed\DashboardCore\Http\Controllers\DashboardController;
use Nreljed\DashboardCore\Http\Controllers\EmailSettingsController;
use Nreljed\DashboardCore\Http\Controllers\MediaController;
use Nreljed\DashboardCore\Http\Controllers\NotificationTestController;
use Nreljed\DashboardCore\Http\Controllers\RecoveryController;
use Nreljed\DashboardCore\Http\Controllers\SettingsController;
use Nreljed\DashboardCore\Http\Controllers\UserAccountController;
use Nreljed\DashboardCore\PublicSite\Controllers\PublicController;
use Nreljed\DashboardCore\Services\SettingsService;

// ═══════════════════════════════════════════════════
// PUBLIC FRONTEND Pages — no auth required
// ═══════════════════════════════════════════════════
Route::middleware('maintenance')->group(function () {
    Route::get('/', fn () => Inertia::render('public/welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]))->name('home');

    Route::get('/about', [PublicController::class, 'about'])->name('about');
    Route::get('/services', [PublicController::class, 'services'])->name('services');
    Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
});

// ═══════════════════════════════════════════════════
// DEPLOY Endpoints — token-protected, no CSRF, rate-limited
// ═══════════════════════════════════════════════════
// check + cache-clear accept GET so cron/probe workflows can poll status.
Route::post('/deploy/upload', [DeployController::class, 'upload'])->middleware('throttle:5,1');
Route::match(['get', 'post'], '/deploy/cache-clear', [DeployController::class, 'cacheClear'])->middleware('throttle:5,1');
Route::match(['get', 'post'], '/deploy/check', [DeployController::class, 'check'])->middleware('throttle:5,1');
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->middleware('throttle:5,1');
Route::post('/deploy/create-admin', [DeployController::class, 'createAdmin'])->middleware('throttle:5,1');
Route::post('/deploy/reset', [DeployController::class, 'resetDatabase'])->middleware('throttle:5,1');

// ═══════════════════════════════════════════════════
// CSP Report Endpoint — receives violation reports
// ═══════════════════════════════════════════════════
Route::post('/csp-report', function (Request $request) {
    report(new Exception('CSP Violation: '.$request->input('csp-report.effective-directive', 'unknown')));

    return response()->json(['status' => 'ok']);
})->middleware('throttle:30,1')->name('csp.report');

// ═══════════════════════════════════════════════════
// MAINTENANCE — status check + bypass (outside maintenance middleware)
// ═══════════════════════════════════════════════════
Route::get('/maintenance/status', function () {
    $maintenance = SettingsService::get('maintenance_mode', '0') === '1';
    $endsAt = SettingsService::get('maintenance_ends_at', '');

    return response()->json([
        'maintenance' => $maintenance,
        'endsAt' => $endsAt ?: null,
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
        ->header('Pragma', 'no-cache');
})->name('maintenance.status');

Route::get('/maintenance/bypass/{token}', function (string $token) {
    $validToken = SettingsService::get('maintenance_bypass_token', '');

    if ($validToken === '' || ! hash_equals($validToken, $token)) {
        abort(404);
    }

    $cookie = Cookie::make(
        'maintenance_bypass',
        $token,
        1440,
        '/',
        null,
        true,
        false,
        false,
        'lax'
    );

    return redirect('/')->withCookie($cookie);
})->name('maintenance.bypass');

// ═══════════════════════════════════════════════════
// MAINTENANCE RECOVERY — email-based lockout prevention
// ═══════════════════════════════════════════════════
Route::get('/maintenance/recovery', [RecoveryController::class, 'showRequestForm'])
    ->name('maintenance.recovery');
Route::post('/maintenance/recovery', [RecoveryController::class, 'requestToken'])
    ->middleware('throttle:3,5')
    ->name('maintenance.recovery.request-token');
Route::get('/maintenance/recovery/{token}', [RecoveryController::class, 'claim'])
    ->name('maintenance.recovery.claim');

// ═══════════════════════════════════════════════════
// DASHBOARD (Backend) Pages — requires auth
// ═══════════════════════════════════════════════════
Route::middleware(['auth', 'maintenance'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // Notifications — page render only (CRUD via JSON API)
    Route::get('/dashboard/notifications', [DashboardController::class, 'notifications'])->name('dashboard.notifications');

    // Lightweight JSON API for notification polling (used by use-notifications hook)
    Route::prefix('/api/notifications')->name('api.notifications.')->middleware('throttle:120,1')->group(function () {
        Route::get('/unread-count', [NotificationPollController::class, 'unreadCount'])->name('unread-count');
        Route::get('/recent', [NotificationPollController::class, 'recent'])->name('recent');
        Route::get('/', [NotificationPollController::class, 'index'])->name('index');
        Route::post('/mark-read', [NotificationPollController::class, 'markRead'])->name('mark-read');
        Route::post('/mark-all-read', [NotificationPollController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('/{id}', [NotificationPollController::class, 'destroy'])->name('destroy');
        Route::post('/mark-bulk', [NotificationPollController::class, 'markBulk'])->middleware('throttle:20,1')->name('mark-bulk');
        Route::post('/delete-bulk', [NotificationPollController::class, 'deleteBulk'])->middleware('throttle:20,1')->name('delete-bulk');
    });

    // Host/Guest mode switch
    Route::post('/dashboard/user/mode', [DashboardController::class, 'switchMode'])->name('dashboard.switch-mode');

    // Impersonation stop — outside admin middleware so impersonated users can reach it
    Route::prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    });

    // User account — consolidated profile + account management
    Route::prefix('/dashboard/account')->group(function () {
        Route::get('/', [UserAccountController::class, 'index'])->name('dashboard.account');

        // Navigation pages
        Route::get('/profile', [UserAccountController::class, 'profile'])->name('dashboard.account.page.profile');
        Route::get('/security', [UserAccountController::class, 'security'])->name('dashboard.account.page.security');
        Route::get('/notifications', [UserAccountController::class, 'notifications'])->name('dashboard.account.page.notifications');
        Route::get('/integrations', [UserAccountController::class, 'integrations'])->name('dashboard.account.page.integrations');
        Route::get('/advanced', [UserAccountController::class, 'advanced'])->name('dashboard.account.page.advanced');

        // Form submission endpoints
        Route::patch('/profile', [UserAccountController::class, 'updateProfile'])->name('dashboard.account.profile');
        Route::patch('/contact', [UserAccountController::class, 'updateContact'])->name('dashboard.account.contact');
        Route::post('/email', [UserAccountController::class, 'updateEmail'])->name('dashboard.account.email');
        Route::post('/password', [UserAccountController::class, 'updatePassword'])->name('dashboard.account.password');
        Route::delete('/', [UserAccountController::class, 'destroy'])->name('dashboard.account.destroy');
        Route::post('/verify-delete-password', [UserAccountController::class, 'verifyDeletePassword'])->name('dashboard.account.verify-delete-password');
        Route::post('/notifications', [UserAccountController::class, 'updateNotificationPreferences'])->name('dashboard.account.notifications');
        Route::put('/address', [UserAccountController::class, 'updateAddress'])->name('dashboard.account.address');
        Route::post('/avatar', [AvatarController::class, 'upload'])->name('dashboard.account.avatar.upload');
        Route::delete('/avatar', [AvatarController::class, 'destroy'])->name('dashboard.account.avatar.destroy');

        // AI Provider Keys (BYOK)
        Route::get('/ai-keys', [AiProviderController::class, 'index'])->name('dashboard.account.ai-keys.index');
        Route::post('/ai-keys', [AiProviderController::class, 'store'])->name('dashboard.account.ai-keys.store');
        Route::put('/ai-keys/{aiProvider}', [AiProviderController::class, 'update'])->name('dashboard.account.ai-keys.update');
        Route::delete('/ai-keys/{aiProvider}', [AiProviderController::class, 'destroy'])->name('dashboard.account.ai-keys.destroy');
        Route::post('/ai-keys/test', [AiProviderController::class, 'test'])->name('dashboard.account.ai-keys.test');
    });

    // Media uploads (auth + verified)
    Route::prefix('/dashboard')->group(function () {
        Route::post('/media', [MediaController::class, 'upload'])->name('dashboard.media.upload');
        Route::delete('/media', [MediaController::class, 'destroy'])->name('dashboard.media.destroy');
    });
});

// ═══════════════════════════════════════════════════
// DASHBOARD ADMIN Pages — requires auth + permission
// ═══════════════════════════════════════════════════
Route::middleware(['auth', 'maintenance', 'throttle:60,1'])->prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
    // System health
    Route::middleware('permission:view-system-health')->group(function () {
        Route::get('/system-health', [AdminController::class, 'systemHealth'])->name('system-health');
        Route::post('/system-health/refresh', [AdminController::class, 'refreshSystemHealth'])->name('system-health.refresh');
    });

    // Database maintenance (danger zone) — admin only, env-gated at controller level
    Route::middleware(['admin', 'permission:manage-options'])->group(function () {
        Route::get('/database', [DatabaseMaintenanceController::class, 'index'])->name('database');
        Route::post('/database/reset', [DatabaseMaintenanceController::class, 'reset'])->name('database.reset');
        Route::post('/database/reseed', [DatabaseMaintenanceController::class, 'reseed'])->name('database.reseed');
    });

    // Settings (general, registration, notifications, security, branding, IP rules)
    Route::middleware('permission:manage-settings')->group(function () {
        Route::prefix('/settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::put('/', [SettingsController::class, 'update'])->name('update');

            Route::get('/general', [SettingsController::class, 'general'])->name('general');
            Route::get('/registration', [SettingsController::class, 'registration'])->name('registration');
            Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
            Route::get('/security', [SettingsController::class, 'security'])->name('security');
            Route::get('/maintenance', [SettingsController::class, 'maintenance'])->name('maintenance');
            Route::get('/dev', [SettingsController::class, 'dev'])->name('dev');
            Route::get('/ip-access', [SettingsController::class, 'ipAccess'])->name('ip-access');

            // Database maintenance toggle (danger zone) — admin only, password-confirmed
            Route::middleware(['admin', 'permission:manage-options'])
                ->post('/dev/database-maintenance-toggle', [SettingsController::class, 'toggleDatabaseMaintenance'])
                ->name('dev.database-maintenance-toggle');

            Route::post('/logo', [SettingsController::class, 'uploadLogo'])->name('logo.upload');
            Route::delete('/logo', [SettingsController::class, 'deleteLogo'])->name('logo.delete');
            Route::post('/favicon', [SettingsController::class, 'uploadFavicon'])->name('favicon.upload');
            Route::delete('/favicon', [SettingsController::class, 'deleteFavicon'])->name('favicon.delete');
        });

        Route::get('/ip-rules', [IpAccessRuleController::class, 'index'])->name('ip-rules.index');
        Route::post('/ip-rules', [IpAccessRuleController::class, 'store'])->name('ip-rules.store');
        Route::put('/ip-rules/{ipAccessRule}', [IpAccessRuleController::class, 'update'])->name('ip-rules.update');
        Route::delete('/ip-rules/{ipAccessRule}', [IpAccessRuleController::class, 'destroy'])->name('ip-rules.destroy');
    });

    // Email settings (dedicated permission)
    Route::middleware('permission:manage-email-settings')->prefix('/settings')->name('settings.')->group(function () {
        Route::get('/email', [SettingsController::class, 'email'])->name('email');
        Route::put('/email', [EmailSettingsController::class, 'update'])->name('email.update');
        Route::post('/email/test', [EmailSettingsController::class, 'sendTest'])->name('email.test');
        Route::post('/email/template-test', [EmailSettingsController::class, 'sendTemplateTest'])->name('email.template-test');
        Route::get('/email/template-preview/{template}', [EmailSettingsController::class, 'previewTemplate'])->name('email.template-preview');
    });

    // User management
    Route::middleware('permission:manage-users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify-email');
        Route::post('/users/{user}/send-reset-link', [UserController::class, 'sendPasswordReset'])->name('users.send-reset-link');
        Route::get('/users/{user}/sessions', [SessionController::class, 'index'])->name('users.sessions');
        Route::delete('/users/{user}/sessions/{session}', [SessionController::class, 'destroy'])->name('users.sessions.destroy');
        Route::post('/users/bulk/delete', [BulkUserController::class, 'delete'])->name('users.bulk.delete');
        Route::post('/users/bulk/verify-email', [BulkUserController::class, 'verifyEmail'])->name('users.bulk.verify-email');
        Route::post('/users/bulk/send-reset', [BulkUserController::class, 'sendPasswordReset'])->name('users.bulk.send-reset');
        Route::get('/users/export', [BulkUserController::class, 'export'])->name('users.export');
    });

    // Role + permission management
    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');

        Route::get('/permissions', [PermissionController::class, 'manage'])->name('permissions.manage');
        Route::post('/permissions', [PermissionController::class, 'toggle'])->name('permissions.toggle');
    });

    // Impersonation start (separate permission)
    Route::middleware('permission:impersonate-users')->post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');

    // Audit logs
    Route::middleware('permission:manage-audit-logs')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->middleware('permission:export-audit-logs')->name('audit-logs.export');
    });

    // Developer tools (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/developer/style-guide', fn () => Inertia::render('dashboard/developer/style-guide'))->name('developer.style-guide');

        Route::get('/notifications/test', [NotificationTestController::class, 'index'])->name('notifications.test');
        Route::post('/notifications/test', [NotificationTestController::class, 'sendTest'])->name('notifications.test-send');
        Route::post('/notifications/test/send-to-all', [NotificationTestController::class, 'sendToAll'])->name('notifications.test-send-all');
        Route::delete('/notifications/test/clear', [NotificationTestController::class, 'clearAll'])->name('notifications.test-clear');
    });
});

// ═══════════════════════════════════════════════════
// AUTH Pages (Breeze) — login, register, password reset
// ═══════════════════════════════════════════════════
require __DIR__.'/auth.php';
