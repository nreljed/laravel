<?php

namespace Nreljed\Platform\PublicSite\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\FormSubmissionService;
use Nreljed\Platform\Support\FormRegistry;

class PublicController extends Controller
{
    public function __construct(
        protected FormSubmissionService $forms,
        protected FormRegistry $registry,
    ) {}

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

    /**
     * Collect a submission for whatever form the route names.
     *
     * Every behaviour that must be identical across forms — honeypot,
     * registry-driven validation, duplicate suppression, attribution capture,
     * persistence and the admin notification fan-out — lives in
     * FormSubmissionService. This controller only resolves the form key.
     *
     * The response is always the same redirect with the same flash key whether
     * the submission was stored, rejected as a duplicate or swallowed by the
     * honeypot, so a bot cannot distinguish success from rejection.
     */
    public function submit(Request $request, ?string $formKey = null): RedirectResponse
    {
        $formKey ??= (string) ($request->route('form_key') ?? '');

        if (! is_string($formKey) || $formKey === '' || $this->registry->get($formKey) === null) {
            abort(404);
        }

        try {
            return $this->forms->submit($formKey, $request);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
