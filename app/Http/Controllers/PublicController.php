<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class PublicController extends Controller
{
    public function about()
    {
        return Inertia::render('Public/About');
    }

    public function services()
    {
        return Inertia::render('Public/Services');
    }

    public function contact()
    {
        return Inertia::render('Public/Contact');
    }
}
