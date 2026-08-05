<?php

namespace Nreljed\DashboardCore\PublicSite\Controllers;

use Inertia\Inertia;
use Nreljed\DashboardCore\Http\Controllers\Controller;

class PublicController extends Controller
{
    public function about()
    {
        return Inertia::render('public/about');
    }

    public function services()
    {
        return Inertia::render('public/services');
    }

    public function contact()
    {
        return Inertia::render('public/contact');
    }
}
