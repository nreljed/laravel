<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\MarketingBroadcast;
use Nreljed\MarketingModule\Resources\MarketingBroadcastResource;
use Nreljed\MarketingModule\Services\BroadcastService;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class MarketingBroadcastsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', MarketingBroadcast::class);

        $broadcasts = MarketingBroadcast::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketingBroadcast $broadcast): array => $this->row($broadcast))
            ->all();

        return $renderer->renderIndex(MarketingBroadcastResource::KEY, $broadcasts);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', MarketingBroadcast::class);

        return $renderer->renderForm(MarketingBroadcastResource::KEY, null, 'POST', route('dashboard.marketing.broadcasts.store'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', MarketingBroadcast::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'channel' => ['required', 'string', 'max:16'],
            'subject' => ['nullable', 'string', 'max:191'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:32'],
            'segment_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $data['created_by'] = $request->user()?->id;
        MarketingBroadcast::create($data);

        return redirect()->route('dashboard.marketing.broadcasts.index')->with('status', 'Broadcast created.');
    }

    public function edit(ResourceRenderer $renderer, MarketingBroadcast $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(MarketingBroadcastResource::KEY, $this->row($record), 'PUT', route('dashboard.marketing.broadcasts.update', $record));
    }

    public function update(Request $request, MarketingBroadcast $record)
    {
        $this->authorize('update', $record);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'channel' => ['required', 'string', 'max:16'],
            'subject' => ['nullable', 'string', 'max:191'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:32'],
            'segment_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $record->update($data);

        return redirect()->route('dashboard.marketing.broadcasts.index')->with('status', 'Broadcast updated.');
    }

    public function destroy(MarketingBroadcast $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Broadcast deleted.');
    }

    public function send(MarketingBroadcast $record, BroadcastService $service)
    {
        $this->authorize('update', $record);

        $service->send($record);

        return redirect()->back()->with('status', 'Broadcast sent.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(MarketingBroadcast $broadcast): array
    {
        return [
            'id' => $broadcast->id,
            'name' => $broadcast->name,
            'channel' => $broadcast->channel?->value,
            'subject' => $broadcast->subject,
            'body_html' => $broadcast->body_html,
            'body_text' => $broadcast->body_text,
            'status' => $broadcast->status?->value,
            'status_label' => $broadcast->status?->label(),
            'segment_id' => $broadcast->segment_id,
            'tag_id' => $broadcast->tag_id,
            'scheduled_at' => $broadcast->scheduled_at?->format('Y-m-d\TH:i'),
            'sent_at' => $broadcast->sent_at?->toDateTimeString(),
            'recipient_count' => $broadcast->recipient_count,
            'delivered_count' => $broadcast->delivered_count,
            'failed_count' => $broadcast->failed_count,
            'created_at' => $broadcast->created_at?->toDateTimeString(),
        ];
    }
}
