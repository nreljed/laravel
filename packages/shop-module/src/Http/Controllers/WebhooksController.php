<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Services\WebhookService;

class WebhooksController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::withCount('calls')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('shop/webhooks/index', [
            'webhooks' => $webhooks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Webhook::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(WebhookEvents::all())],
            'headers' => 'nullable|array',
            'timeout' => 'integer|min:1|max:60',
            'retry_attempts' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:1|max:300',
        ]);

        Webhook::create($validated);

        return redirect()->back()->with('flash', ['success' => 'Webhook created.']);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(WebhookEvents::all())],
            'headers' => 'nullable|array',
            'timeout' => 'integer|min:1|max:60',
            'retry_attempts' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:1|max:300',
        ]);

        $webhook->update($validated);

        return redirect()->back()->with('flash', ['success' => 'Webhook updated.']);
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return redirect()->back()->with('flash', ['success' => 'Webhook deleted.']);
    }

    public function toggleActive(Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $webhook->update(['is_active' => ! $webhook->is_active]);

        return redirect()->back()->with('flash', [
            'success' => $webhook->is_active ? 'Webhook activated.' : 'Webhook deactivated.',
        ]);
    }

    public function test(Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $this->webhookService->dispatchAndWait($webhook, WebhookEvents::WEBHOOK_TEST, [
            'event' => WebhookEvents::WEBHOOK_TEST,
            'data' => [
                'message' => 'Test webhook from CostBreaker 360',
                'timestamp' => now()->toISOString(),
            ],
        ]);

        return redirect()->back()->with('flash', ['success' => 'Test webhook dispatched.']);
    }

    public function calls(Webhook $webhook): Response
    {
        $this->authorize('viewAny', Webhook::class);

        $calls = $webhook->calls()
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('shop/webhooks/calls', [
            'webhook' => $webhook->only('id', 'name'),
            'calls' => $calls,
        ]);
    }
}
