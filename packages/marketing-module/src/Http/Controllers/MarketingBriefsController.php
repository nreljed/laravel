<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\MarketingModule\Resources\MarketingBriefResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class MarketingBriefsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', MarketingBrief::class);

        $briefs = MarketingBrief::query()
            ->with('plan:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketingBrief $brief): array => $this->row($brief))
            ->all();

        return $renderer->renderIndex(MarketingBriefResource::KEY, $briefs);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', MarketingBrief::class);

        return $renderer->renderForm(MarketingBriefResource::KEY, null, 'POST', route('dashboard.marketing.briefs.store'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', MarketingBrief::class);

        $data = $request->validate([
            'marketing_plan_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'objective' => ['nullable', 'string', 'max:32'],
            'audience' => ['nullable', 'string'],
            'key_message' => ['nullable', 'string'],
            'channels' => ['nullable', 'array'],
            'status' => ['required', 'string', 'max:32'],
            'due_at' => ['nullable', 'date'],
        ]);

        MarketingBrief::create($data);

        return redirect()->route('dashboard.marketing.briefs.index')->with('status', 'Brief created.');
    }

    public function edit(ResourceRenderer $renderer, MarketingBrief $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(MarketingBriefResource::KEY, $this->row($record), 'PUT', route('dashboard.marketing.briefs.update', $record));
    }

    public function update(Request $request, MarketingBrief $record)
    {
        $this->authorize('update', $record);

        $data = $request->validate([
            'marketing_plan_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'objective' => ['nullable', 'string', 'max:32'],
            'audience' => ['nullable', 'string'],
            'key_message' => ['nullable', 'string'],
            'channels' => ['nullable', 'array'],
            'status' => ['required', 'string', 'max:32'],
            'due_at' => ['nullable', 'date'],
        ]);

        $record->update($data);

        return redirect()->route('dashboard.marketing.briefs.index')->with('status', 'Brief updated.');
    }

    public function destroy(MarketingBrief $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Brief deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(MarketingBrief $brief): array
    {
        return [
            'id' => $brief->id,
            'marketing_plan_id' => $brief->marketing_plan_id,
            'plan_name' => $brief->plan?->name,
            'title' => $brief->title,
            'objective' => $brief->objective?->value,
            'audience' => $brief->audience,
            'key_message' => $brief->key_message,
            'channels' => $brief->channels,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'due_at' => $brief->due_at?->format('Y-m-d\TH:i'),
            'created_at' => $brief->created_at?->toDateTimeString(),
        ];
    }
}
