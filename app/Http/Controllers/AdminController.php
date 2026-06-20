<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function settings()
    {
        return Inertia::render('Admin/Settings');
    }

    public function systemHealth(SystemHealthService $systemHealth)
    {
        return Inertia::render('Admin/SystemHealth', $systemHealth->getDiagnostics());
    }
}
