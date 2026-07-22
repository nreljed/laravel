<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UploadFaviconRequest;
use App\Http\Requests\UploadLogoRequest;
use App\Models\Role;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        if (! Schema::hasTable('settings')) {
            return Inertia::render('dashboard/admin/settings', [
                'settings' => new \stdClass,
            ]);
        }

        return Inertia::render('dashboard/admin/settings', [
            'settings' => SettingsService::getAllGrouped(),
            'roles' => Schema::hasTable('roles')
                ? Role::where('slug', '!=', 'admin')->select('id', 'name', 'slug')->orderBy('name')->get()
                : [],
        ]);
    }

    public function update(UpdateSettingsRequest $request): Response|RedirectResponse
    {
        if (! Schema::hasTable('settings')) {
            return redirect()->back()->with('status', 'Settings table not yet migrated.');
        }

        $validated = array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $request->validated());

        SettingsService::setMany($validated);
        SettingsService::clearCache();

        return redirect()->back()->with('status', 'Settings saved successfully.');
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
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
