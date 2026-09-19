<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Requests\Account\RestoreAccountDataRequest;
use Nreljed\Platform\Services\AuditService;
use Nreljed\Platform\Services\FeatureRegistry;

class AccountDataController extends Controller
{
    public function __construct(
        protected FeatureRegistry $registry,
    ) {}

    /**
     * List every data backup module declared by enabled features.
     */
    public function index(): Response
    {
        $user = request()->user();

        $modules = collect($this->registry->getDataBackupModules())
            ->map(function (array $module) use ($user) {
                $capability = $module['capability'];

                return $capability->toArray() + [
                    'provider' => $module['provider']->slug(),
                    'summary' => $capability->supportsExport()
                        ? $module['provider']->dataBackupSummary($capability->getKey(), $user)
                        : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dashboard/account/data', [
            'modules' => $modules,
            'status' => session('status'),
        ]);
    }

    /**
     * Download the user's own data for the given module as a JSON backup file.
     */
    public function export(string $module): JsonResponse
    {
        $resolved = $this->registry->resolveDataBackupModule($module);

        abort_unless($resolved !== null, 404, 'Unknown data module.');

        $provider = $resolved['provider'];
        $capability = $resolved['capability'];

        if (! $capability->supportsExport()) {
            abort(403, 'This module does not support data export.');
        }

        $user = request()->user();

        $payload = [
            'app' => config('app.name'),
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'user' => [
                'id' => (string) $user->id,
                'username' => $user->username,
            ],
            'module' => [
                'provider' => $provider->slug(),
                'key' => $capability->getKey(),
                'label' => $capability->getLabel(),
            ],
            'data' => $provider->exportDataBackup($capability->getKey(), $user),
        ];

        $filename = sprintf(
            '%s-%s-%s.json',
            Str::slug($capability->getKey()),
            (string) $user->username,
            now()->format('Y-m-d-His'),
        );

        AuditService::log(AuditAction::DataExported, $user->id, [
            'module' => $capability->getKey(),
        ]);

        return response()
            ->json($payload)
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * Replace the user's data for the given module with a previously exported file.
     */
    public function restore(RestoreAccountDataRequest $request, string $module): RedirectResponse
    {
        $resolved = $this->registry->resolveDataBackupModule($module);

        abort_unless($resolved !== null, 404, 'Unknown data module.');

        $provider = $resolved['provider'];
        $capability = $resolved['capability'];

        if (! $capability->supportsRestore()) {
            abort(403, 'This module does not support data restore.');
        }

        $user = $request->user();

        try {
            $payload = json_decode(
                File::get($request->file('backup')->getRealPath()),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'backup' => 'The backup file is not valid JSON.',
            ]);
        }

        if (! $this->isValidEnvelope($payload, $provider->slug(), $module)) {
            throw ValidationException::withMessages([
                'backup' => 'The backup file does not match this data module.',
            ]);
        }

        $imported = DB::transaction(function () use ($provider, $capability, $user, $payload) {
            return $provider->importDataBackup($capability->getKey(), $user, $payload['data']);
        });

        AuditService::log(AuditAction::DataRestored, $user->id, [
            'module' => $capability->getKey(),
            'records' => $imported,
        ]);

        return redirect()
            ->route('dashboard.account.page.data')
            ->with('status', "Your {$capability->getLabel()} data has been restored.");
    }

    /**
     * Envelope contract: versioned payload we wrote at export time, matching
     * the requested module and its owning feature. A file from another module
     * (or a hand-edited one) is rejected before any provider code runs.
     */
    private function isValidEnvelope(mixed $payload, string $providerSlug, string $moduleKey): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        return ($payload['version'] ?? null) === 1
            && ($payload['module']['provider'] ?? null) === $providerSlug
            && ($payload['module']['key'] ?? null) === $moduleKey
            && is_array($payload['data'] ?? null);
    }
}
