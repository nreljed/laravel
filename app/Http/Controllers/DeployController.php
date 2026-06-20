<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DeployController extends Controller
{
    protected function verifyToken(Request $request): void
    {
        $token = $request->input('token', $request->query('token'));
        $expected = config('app.deploy_token');

        if (!$expected || $token !== $expected) {
            abort(403, 'Invalid or missing deploy token.');
        }
    }

    public function updateEnv(Request $request)
    {
        $this->verifyToken($request);

        $content = $request->input('content');

        if (!$content || trim($content) === '') {
            return response()->json(['error' => 'No content provided'], 400);
        }

        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            copy($envPath, $envPath . '.backup.' . now()->timestamp);
        }

        file_put_contents($envPath, $content);

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'message' => '.env written']);
    }

    public function cacheClear(Request $request)
    {
        $this->verifyToken($request);

        // Wipe all cached files first to break the chicken-and-egg cycle
        $this->wipeCache();

        // Now that cache is gone, run fresh migrations
        Artisan::call('migrate', ['--force' => true]);
        $output['migrate'] = Artisan::output();

        Artisan::call('cache:clear');
        $output['cache:clear'] = Artisan::output();

        // Re-cache for production
        Artisan::call('config:cache');
        $output['config:cache'] = Artisan::output();

        Artisan::call('route:cache');
        $output['route:cache'] = Artisan::output();

        Artisan::call('view:cache');
        $output['view:cache'] = Artisan::output();

        return response()->json(['success' => true, 'output' => $output]);
    }

    private function wipeCache(): void
    {
        $paths = [
            base_path('bootstrap/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/views'),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                File::delete(File::glob("{$path}/*.php"));
                File::delete(File::glob("{$path}/*.json"));
            }
        }
    }
}
