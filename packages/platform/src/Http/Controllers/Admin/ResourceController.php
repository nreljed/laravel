<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\FeatureRegistry;

/**
 * Admin inventory of every descriptor declared by enabled features.
 *
 * The listed resources keep their CRUD endpoints with the owning feature —
 * the routes shown here are the descriptors' declared route names, not this
 * controller's. Providers render the generic kernel pages
 * (dashboard/admin/resources/index|form) themselves via ResourceRenderer;
 * this page only catalogues them.
 */
class ResourceController extends Controller
{
    public function index(FeatureRegistry $registry): Response
    {
        return Inertia::render('dashboard/admin/resources/inventory', [
            'resources' => $registry->getAllAdminResourcesArray(),
        ]);
    }
}
