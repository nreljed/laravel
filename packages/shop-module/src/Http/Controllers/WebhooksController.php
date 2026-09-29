<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Resources\WebhookResource;
use Nreljed\ShopModule\Services\WebhookService;

class WebhooksController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    public function index(ResourceRenderer $renderer): Response
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::withCount('calls')
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $webhook): array => [
                ...$webhook->toArray(),
                // The `events` column is an array on the model; the kernel table
                // renders raw values, so the index hands a readable string while
                // the edit form still receives the real array from the record.
                'events' => implode(', ', $webhook->events ?? []),
            ])
            ->all();

        return $renderer->renderIndex(WebhookResource::KEY, $webhooks);
    }

    public function create(ResourceRenderer $renderer): Response
    {
        $this->authorize('create', Webhook::class);

        return $renderer->renderForm(
            WebhookResource::KEY,
            null,
            'POST',
            route('dashboard.shop.webhooks.store'),
        );
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
            'is_active' => 'boolean',
        ]);

        Webhook::create($validated);

        return redirect()->back()->with('flash', ['success' => 'Webhook created.']);
    }

    public function edit(ResourceRenderer $renderer, Webhook $record): Response
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            WebhookResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.webhooks.update', $record),
        );
    }

    public function update(Request $request, Webhook $record): RedirectResponse
    {
        $this->authorize('update', $record);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(WebhookEvents::all())],
            'headers' => 'nullable|array',
            'timeout' => 'integer|min:1|max:60',
            'retry_attempts' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:1|max:300',
            'is_active' => 'boolean',
        ]);

        $record->update($validated);

        return redirect()->back()->with('flash', ['success' => 'Webhook updated.']);
    }

    public function destroy(Webhook $record): RedirectResponse
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('flash', ['success' => 'Webhook deleted.']);
    }

    public function toggleActive(Webhook $record): RedirectResponse
    {
        $this->authorize('update', $record);

        $record->update(['is_active' => ! $record->is_active]);

        return redirect()->back()->with('flash', [
            'success' => $record->is_active ? 'Webhook activated.' : 'Webhook deactivated.',
        ]);
    }

    public function test(Webhook $record): RedirectResponse
    {
        $this->authorize('update', $record);

        $this->webhookService->dispatchAndWait($record, WebhookEvents::WEBHOOK_TEST, [
            'event' => WebhookEvents::WEBHOOK_TEST,
            'data' => [
                'message' => 'Test webhook from CostBreaker 360',
                'timestamp' => now()->toISOString(),
            ],
        ]);

        return redirect()->back()->with('flash', ['success' => 'Test webhook dispatched.']);
    }

    public function calls(Webhook $record): Response
    {
        $this->authorize('viewAny', Webhook::class);

        $calls = $record->calls()
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('shop/webhooks/calls', [
            'webhook' => $record->only('id', 'name'),
            'calls' => $calls,
        ]);
    }
}
