<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Exceptions\DatabaseMaintenanceException;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\DatabaseReseedRequest;
use Nreljed\DashboardCore\Http\Requests\DatabaseResetRequest;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;

class DatabaseMaintenanceController extends Controller
{
    public function __construct(private readonly DatabaseMaintenanceService $service) {}

    public function index(): Response
    {
        abort_unless($this->service->isEnabled(), 403);

        return Inertia::render('dashboard/admin/database-maintenance', [
            'status' => $this->service->status(),
        ]);
    }

    public function reset(DatabaseResetRequest $request): RedirectResponse
    {
        abort_unless($this->service->isEnabled(), 403);

        try {
            $this->service->resetDatabase(
                withSeed: true,
                purgeMedia: $request->boolean('purge_media'),
                userId: $request->user()->id,
            );
        } catch (DatabaseMaintenanceException $e) {
            $this->endSession($request);

            return redirect()->route('login')->with('flash', ['error' => 'Database reset failed: '.$e->getMessage()]);
        }

        $this->endSession($request);

        return redirect()->route('login')->with('flash', ['success' => 'Database has been reset and reseeded. Sign in with your admin credentials to continue.']);
    }

    public function reseed(DatabaseReseedRequest $request): RedirectResponse
    {
        abort_unless($this->service->isEnabled(), 403);

        try {
            $this->service->resetDatabase(
                withSeed: false,
                purgeMedia: false,
                userId: $request->user()->id,
            );
        } catch (DatabaseMaintenanceException $e) {
            return back()->with('flash', ['error' => 'Database reseed failed: '.$e->getMessage()]);
        }

        return back()->with('flash', ['success' => 'Seeders were re-run successfully.']);
    }

    private function endSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
