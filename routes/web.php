<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BulkUserController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\NotificationPollController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationTestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ═══════════════════════════════════════════════════
// FRONTEND (Public) Pages — no auth required
// ═══════════════════════════════════════════════════
Route::get('/', function () {
    return Inertia::render('public/welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');

// ═══════════════════════════════════════════════════
// DASHBOARD (Backend) Pages — requires auth
// ═══════════════════════════════════════════════════
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // Notifications — page render only (CRUD via JSON API)
    Route::get('/dashboard/notifications', [DashboardController::class, 'notifications'])->name('dashboard.notifications');

    // Lightweight JSON API for notification polling (used by use-notifications hook)
    Route::prefix('/api/notifications')->name('api.notifications.')->group(function () {
        Route::get('/unread-count', [NotificationPollController::class, 'unreadCount'])->name('unread-count');
        Route::get('/recent', [NotificationPollController::class, 'recent'])->name('recent');
        Route::get('/', [NotificationPollController::class, 'index'])->name('index');
        Route::post('/mark-read', [NotificationPollController::class, 'markRead'])->name('mark-read');
        Route::post('/mark-all-read', [NotificationPollController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('/{id}', [NotificationPollController::class, 'destroy'])->name('destroy');
        Route::post('/mark-bulk', [NotificationPollController::class, 'markBulk'])->name('mark-bulk');
        Route::post('/delete-bulk', [NotificationPollController::class, 'deleteBulk'])->name('delete-bulk');
    });

    // Developer tools (admin-only)
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard/developer/test-page', function () {
            return Inertia::render('dashboard/developer/test-page');
        })->name('dashboard.developer.test-page');

        Route::get('/dashboard/notifications/test', [NotificationTestController::class, 'index'])->name('dashboard.notifications.test');
        Route::post('/dashboard/notifications/test', [NotificationTestController::class, 'sendTest'])->name('dashboard.notifications.test-send');
        Route::post('/dashboard/notifications/test/send-to-all', [NotificationTestController::class, 'sendToAll'])->name('dashboard.notifications.test-send-all');
        Route::delete('/dashboard/notifications/test/clear', [NotificationTestController::class, 'clearAll'])->name('dashboard.notifications.test-clear');
    });

    // Host/Guest mode switch
    Route::post('/dashboard/user/mode', [DashboardController::class, 'switchMode'])->name('dashboard.switch-mode');

    // Impersonation stop — outside admin middleware so impersonated users can reach it
    Route::prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    });

    // Account management
    Route::prefix('/dashboard/account')->name('dashboard.')->group(function () {
        Route::get('/', [AccountController::class, 'edit'])->name('account');
        Route::post('/email', [AccountController::class, 'updateEmail'])->name('account.update-email');
        Route::post('/password', [AccountController::class, 'updatePassword'])->name('account.update-password');
        Route::post('/delete', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::post('/verify-delete-password', [AccountController::class, 'verifyDeletePassword'])->name('account.verify-delete-password');
        Route::post('/notification-preferences', [AccountController::class, 'updateNotificationPreferences'])->name('account.notification-preferences');
        Route::put('/address', [AccountController::class, 'updateAddress'])->name('account.address');
        Route::get('/tokens', [AccountController::class, 'tokens'])->name('account.tokens');
        Route::post('/tokens', [AccountController::class, 'createToken'])->name('account.tokens.create');
        Route::delete('/tokens/{tokenId}', [AccountController::class, 'deleteToken'])->name('account.tokens.destroy');
    });

    // Profile management
    Route::prefix('/dashboard/profile')->name('dashboard.profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        Route::post('/avatar', [AvatarController::class, 'upload'])->name('avatar.upload');
        Route::delete('/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
    });
});

// ═══════════════════════════════════════════════════
// DASHBOARD ADMIN Pages — requires auth + admin
// ═══════════════════════════════════════════════════
Route::middleware(['auth', 'admin', 'throttle:60,1'])->prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/system-health', [AdminController::class, 'systemHealth'])->name('system-health');
    Route::put('/modules/{module}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');

    // User management
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

    // Role management
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Impersonation start
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');

    // Bulk operations
    Route::post('/users/bulk/delete', [BulkUserController::class, 'delete'])->name('users.bulk.delete');
    Route::get('/users/export', [BulkUserController::class, 'export'])->name('users.export');

    // Audit logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Permission management
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

    Route::prefix('/settings')->name('settings.')->group(function () {
        Route::put('/email', [EmailSettingsController::class, 'update'])->name('email.update');
        Route::post('/email/test', [EmailSettingsController::class, 'sendTest'])->name('email.test');
        Route::post('/email/template-test', [EmailSettingsController::class, 'sendTemplateTest'])->name('email.template-test');
        Route::get('/email/template-preview/{template}', [EmailSettingsController::class, 'previewTemplate'])->name('email.template-preview');
        Route::post('/logo', [SettingsController::class, 'uploadLogo'])->name('logo.upload');
        Route::delete('/logo', [SettingsController::class, 'deleteLogo'])->name('logo.delete');
    });
});

// ═══════════════════════════════════════════════════
// AUTH Pages (Breeze) — login, register, password reset
// ═══════════════════════════════════════════════════
require __DIR__.'/auth.php';

// ═══════════════════════════════════════════════════
// DEPLOY Endpoints — token-protected, no CSRF
// ═══════════════════════════════════════════════════
Route::post('/deploy/upload', [DeployController::class, 'upload']);
Route::post('/deploy/update-env', [DeployController::class, 'updateEnv']);
Route::get('/deploy/cache-clear', [DeployController::class, 'cacheClear']);
Route::get('/deploy/check', [DeployController::class, 'check']);

// Module routes (Properties, Bookings) are loaded by their respective
// Module classes via HasRoutes trait — no duplication needed here.
