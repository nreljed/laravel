<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\MarketingExperiment;
use Nreljed\MarketingModule\Resources\MarketingExperimentResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class MarketingExperimentsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', MarketingExperiment::class);

        $experiments = MarketingExperiment::query()
            ->with(['brief:id,title', 'campaign:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketingExperiment $experiment): array => $this->row($experiment))
            ->all();

        return $renderer->renderIndex(MarketingExperimentResource::KEY, $experiments);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', MarketingExperiment::class);

        return $renderer->renderForm(MarketingExperimentResource::KEY, null, 'POST', route('dashboard.marketing.experiments.store'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', MarketingExperiment::class);

        $data = $request->validate([
            'marketing_brief_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:191'],
            'hypothesis' => ['nullable', 'string'],
            'success_metric' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:32'],
            'variants' => ['nullable', 'array'],
            'winner' => ['nullable', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        MarketingExperiment::create($data);

        return redirect()->route('dashboard.marketing.experiments.index')->with('status', 'Experiment created.');
    }

    public function edit(ResourceRenderer $renderer, MarketingExperiment $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(MarketingExperimentResource::KEY, $this->row($record), 'PUT', route('dashboard.marketing.experiments.update', $record));
    }

    public function update(Request $request, MarketingExperiment $record)
    {
        $this->authorize('update', $record);

        $data = $request->validate([
            'marketing_brief_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:191'],
            'hypothesis' => ['nullable', 'string'],
            'success_metric' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:32'],
            'variants' => ['nullable', 'array'],
            'winner' => ['nullable', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $record->update($data);

        return redirect()->route('dashboard.marketing.experiments.index')->with('status', 'Experiment updated.');
    }

    public function destroy(MarketingExperiment $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Experiment deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(MarketingExperiment $experiment): array
    {
        return [
            'id' => $experiment->id,
            'marketing_brief_id' => $experiment->marketing_brief_id,
            'brief_title' => $experiment->brief?->title,
            'campaign_id' => $experiment->campaign_id,
            'campaign_name' => $experiment->campaign?->name,
            'name' => $experiment->name,
            'hypothesis' => $experiment->hypothesis,
            'success_metric' => $experiment->success_metric,
            'status' => $experiment->status?->value,
            'status_label' => $experiment->status?->label(),
            'variants' => $experiment->variants,
            'winner' => $experiment->winner,
            'started_at' => $experiment->started_at?->format('Y-m-d\TH:i'),
            'ended_at' => $experiment->ended_at?->format('Y-m-d\TH:i'),
            'created_at' => $experiment->created_at?->toDateTimeString(),
        ];
    }
}
