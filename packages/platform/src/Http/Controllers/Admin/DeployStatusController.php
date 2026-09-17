<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\DeployService;

class DeployStatusController extends Controller
{
    public function __construct(
        private readonly DeployService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('dashboard/admin/deploy-status', [
            'deploy' => $this->service->check(),
        ]);
    }

    public function check(): JsonResponse
    {
        return response()->json($this->service->check());
    }
}
