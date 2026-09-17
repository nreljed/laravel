<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\DeployHistoryService;

class DeployHistoryController extends Controller
{
    public function __construct(
        private readonly DeployHistoryService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('dashboard/admin/deploy-history', [
            'releases' => $this->service->all(),
        ]);
    }
}
