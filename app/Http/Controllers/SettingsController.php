<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UploadLogoRequest;
use App\Models\Module;
use App\Models\Role;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        if (! Schema::hasTable('settings')) {
            return Inertia::render('dashboard/admin/settings', [
                'settings' => new \stdClass,
                'availableModules' => [],
            ]);
        }

        return Inertia::render('dashboard/admin/settings', [
            'settings' => SettingsService::getAllGrouped(),
            'availableModules' => Schema::hasTable('modules')
                ? Module::select('id', 'name', 'slug', 'enabled', 'version')->get()
                : [],
            'roles' => Schema::hasTable('roles')
                ? Role::where('slug', '!=', 'admin')->select('id', 'name', 'slug')->orderBy('name')->get()
                : [],
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        if (! Schema::hasTable('settings')) {
            return redirect()->back()->with('status', 'Settings table not yet migrated.');
        }

        $validated = array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $request->validated());

        SettingsService::setMany($validated);
        SettingsService::clearCache();

        return redirect()->back()->with('status', 'Settings saved successfully.');
    }

    public function uploadLogo(UploadLogoRequest $request)
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

    public function deleteLogo()
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
}
