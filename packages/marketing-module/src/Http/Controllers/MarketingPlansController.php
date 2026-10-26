<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Agents\MarketingStrategistAgent;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\MarketingModule\Resources\MarketingPlanResource;
use Nreljed\MarketingModule\Services\MarketingPlanService;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Support\ResourceRenderer;

class MarketingPlansController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', MarketingPlan::class);

        $plans = MarketingPlan::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketingPlan $plan): array => $this->row($plan))
            ->all();

        return $renderer->renderIndex(MarketingPlanResource::KEY, $plans);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', MarketingPlan::class);

        return $renderer->renderForm(MarketingPlanResource::KEY, null, 'POST', route('dashboard.marketing.plans.store'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', MarketingPlan::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'objective' => ['required', 'string', 'max:32'],
            'status' => ['required', 'string', 'max:32'],
            'audience' => ['nullable', 'string'],
            'cadence' => ['nullable', 'string', 'max:64'],
            'constraints' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        MarketingPlan::create($data);

        return redirect()->route('dashboard.marketing.plans.index')->with('status', 'Plan created.');
    }

    public function edit(ResourceRenderer $renderer, MarketingPlan $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(MarketingPlanResource::KEY, $this->row($record), 'PUT', route('dashboard.marketing.plans.update', $record));
    }

    public function update(Request $request, MarketingPlan $record)
    {
        $this->authorize('update', $record);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'objective' => ['required', 'string', 'max:32'],
            'status' => ['required', 'string', 'max:32'],
            'audience' => ['nullable', 'string'],
            'cadence' => ['nullable', 'string', 'max:64'],
            'constraints' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        if ($data['status'] === 'approved' && $record->approved_at === null) {
            $data['approved_by'] = $request->user()?->id;
            $data['approved_at'] = now();
        }

        $record->update($data);

        return redirect()->route('dashboard.marketing.plans.index')->with('status', 'Plan updated.');
    }

    public function generate(Request $request, MarketingStrategistAgent $agent, MarketingPlanService $plans)
    {
        $this->authorize('create', MarketingPlan::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $goal = $request->input('goal');

        try {
            $plans->generate($user, $days, is_string($goal) ? $goal : null, $agent);

            return redirect()->route('dashboard.marketing.plans.index')->with('status', 'AI draft plan created for review.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Could not generate a plan: '.$e->getMessage());
        }
    }

    public function destroy(MarketingPlan $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Plan deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(MarketingPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'objective' => $plan->objective?->value,
            'objective_label' => $plan->objective?->label(),
            'status' => $plan->status?->value,
            'status_label' => $plan->status?->label(),
            'audience' => $plan->audience,
            'cadence' => $plan->cadence,
            'constraints' => $plan->constraints,
            'starts_at' => $plan->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $plan->ends_at?->format('Y-m-d\TH:i'),
            'created_at' => $plan->created_at?->toDateTimeString(),
        ];
    }
}
