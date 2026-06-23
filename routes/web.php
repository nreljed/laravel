<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationTestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ═══════════════════════════════════════════════════
// FRONTEND (Public) Pages — no auth required
// ═══════════════════════════════════════════════════
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');

// ═══════════════════════════════════════════════════
// DASHBOARD (Backend) Pages — requires auth
// ═══════════════════════════════════════════════════
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // Notifications
    Route::get('/dashboard/notifications', [DashboardController::class, 'notifications'])->name('dashboard.notifications');
    Route::put('/dashboard/notifications/mark-read', [DashboardController::class, 'markRead'])->name('dashboard.notifications.mark-read');
    Route::post('/dashboard/notifications/mark-all-read', [DashboardController::class, 'markAllRead'])->name('dashboard.notifications.mark-all-read');
    Route::delete('/dashboard/notifications/{id}', [DashboardController::class, 'destroyNotification'])->name('dashboard.notifications.destroy');
    Route::post('/dashboard/notifications/mark-bulk', [DashboardController::class, 'markBulk'])->name('dashboard.notifications.mark-bulk');
    Route::post('/dashboard/notifications/delete-bulk', [DashboardController::class, 'deleteBulk'])->name('dashboard.notifications.delete-bulk');

    // Developer tools (admin-only)
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard/developer/test-page', function () {
            return Inertia::render('TestPage');
        })->name('dashboard.developer.test-page');

        Route::get('/dashboard/notifications/test', [NotificationTestController::class, 'index'])->name('dashboard.notifications.test');
        Route::post('/dashboard/notifications/test', [NotificationTestController::class, 'sendTest'])->name('dashboard.notifications.test-send');
        Route::post('/dashboard/notifications/test/send-to-all', [NotificationTestController::class, 'sendToAll'])->name('dashboard.notifications.test-send-all');
        Route::delete('/dashboard/notifications/test/clear', [NotificationTestController::class, 'clearAll'])->name('dashboard.notifications.test-clear');
    });

    // Host/Guest mode switch
    Route::post('/dashboard/user/mode', [DashboardController::class, 'switchMode'])->name('dashboard.switch-mode');

    // Account management
    Route::prefix('/dashboard/account')->name('dashboard.')->group(function () {
        Route::get('/', [AccountController::class, 'edit'])->name('account');
        Route::post('/email', [AccountController::class, 'updateEmail'])->name('account.update-email');
        Route::post('/password', [AccountController::class, 'updatePassword'])->name('account.update-password');
        Route::post('/delete', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::post('/verify-delete-password', [AccountController::class, 'verifyDeletePassword'])->name('account.verify-delete-password');
        Route::post('/notification-preferences', [AccountController::class, 'updateNotificationPreferences'])->name('account.notification-preferences');
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
Route::middleware(['auth', 'admin'])->prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
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

    // Role management
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    Route::prefix('/settings')->name('settings.')->group(function () {
        Route::put('/email', [App\Http\Controllers\EmailSettingsController::class, 'update'])->name('email.update');
        Route::post('/email/test', [App\Http\Controllers\EmailSettingsController::class, 'sendTest'])->name('email.test');
        Route::post('/email/template-test', [App\Http\Controllers\EmailSettingsController::class, 'sendTemplateTest'])->name('email.template-test');
        Route::get('/email/template-preview/{template}', [App\Http\Controllers\EmailSettingsController::class, 'previewTemplate'])->name('email.template-preview');
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
Route::post('/deploy/update-env', [App\Http\Controllers\DeployController::class, 'updateEnv']);
Route::get('/deploy/cache-clear', [App\Http\Controllers\DeployController::class, 'cacheClear']);
Route::get('/deploy/check', [App\Http\Controllers\DeployController::class, 'check']);

// Module routes (Properties, Bookings) are loaded by their respective
// Module classes via HasRoutes trait — no duplication needed here.
