<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ensures an admin user exists before a seeder runs.
 *
 * The maintenance reset flow seeds demo groups before app:create-admin
 * runs, so demo seeders that attribute records to an admin user must
 * bootstrap one themselves. Re-running the flow is a no-op when an admin
 * already exists.
 */
trait BootstrapsAdmin
{
    private function ensureAdminUser(): User
    {
        $admin = User::whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first();

        if (! $admin) {
            Artisan::call('app:create-admin', [
                '--no-interaction' => true,
                '--email' => config('admin.email') ?: 'nstudio.webdesign@gmail.com',
                '--password' => config('admin.password') ?: 'azerty123/*',
                '--name' => config('admin.name') ?: 'nstudio',
                '--username' => config('admin.username') ?: 'nstudio',
            ]);

            if (trim(Artisan::output()) !== '') {
                $this->command->line(trim(Artisan::output()));
            }

            $admin = User::whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->firstOrFail();
        }

        if (empty($admin->display_name) || empty($admin->avatar)) {
            $admin->update([
                'first_name' => $admin->first_name ?: 'Nstudio',
                'last_name' => $admin->last_name ?: 'Webdesign',
                'display_name' => $admin->display_name ?: ($admin->name ?: 'nstudio'),
                'bio' => $admin->bio ?: 'Shop owner and platform administrator for the Nstudio store.',
                'phone' => $admin->phone ?: '+1 555 010 0100',
                'contact_email' => $admin->contact_email ?: ($admin->email ?: 'nstudio.webdesign@gmail.com'),
                'social_links' => $admin->social_links ?: [
                    'twitter' => 'https://twitter.com/nstudioweb',
                    'facebook' => 'https://facebook.com/nstudiowebdesign',
                    'instagram' => 'https://instagram.com/nstudioweb',
                    'linkedin' => 'https://linkedin.com/in/nstudiowebdesign',
                    'tiktok' => null,
                    'youtube' => null,
                    'whatsapp' => null,
                ],
                'avatar' => $admin->avatar ?: $this->downloadAdminAvatar(),
            ]);
        }

        return $admin->fresh();
    }

    private function downloadAdminAvatar(string $seed = 'nstudio'): ?string
    {
        try {
            $response = Http::timeout(15)->get("https://picsum.photos/seed/{$seed}/200/200");

            if (! $response->successful()) {
                return null;
            }

            $filename = $seed.'-'.Str::random(8).'.jpg';

            Storage::disk('avatars')->put($filename, $response->body());

            return $filename;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
