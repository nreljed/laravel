<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function settings()
    {
        return Inertia::render('dashboard/admin/settings');
    }

    public function systemHealth(SystemHealthService $systemHealth)
    {
        return Inertia::render('dashboard/admin/system-health', $systemHealth->getAll());
    }
}
