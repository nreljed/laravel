<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Requests\StoreWorkflowRequest;
use Nreljed\MarketingModule\Resources\WorkflowResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class WorkflowsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Workflow::class);

        $workflows = Workflow::query()
            ->with(['tag', 'conditionTag'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Workflow $workflow): array => $this->row($workflow))
            ->all();

        return $renderer->renderIndex(WorkflowResource::KEY, $workflows);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Workflow::class);

        return $renderer->renderForm(
            WorkflowResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.workflows.store'),
        );
    }

    public function store(StoreWorkflowRequest $request)
    {
        $this->authorize('create', Workflow::class);

        Workflow::create($request->validated());

        return redirect()
            ->route('dashboard.marketing.workflows.index')
            ->with('status', 'Workflow created.');
    }

    public function edit(ResourceRenderer $renderer, Workflow $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            WorkflowResource::KEY,
            $this->row($record->load(['tag', 'conditionTag'])),
            'PUT',
            route('dashboard.marketing.workflows.update', $record),
        );
    }

    public function update(StoreWorkflowRequest $request, Workflow $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()
            ->route('dashboard.marketing.workflows.index')
            ->with('status', 'Workflow updated.');
    }

    public function destroy(Workflow $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Workflow deleted.');
    }

    /**
     * Tags the workflow form may pick as an action target or condition.
     */
    public function tagOptions(): JsonResponse
    {
        $this->authorize('viewAny', Workflow::class);

        return response()->json(
            Tag::query()
                ->orderBy('name')
                ->get()
                ->map(static fn (Tag $tag): array => [
                    'value' => $tag->id,
                    'label' => $tag->name,
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Workflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'trigger' => $workflow->trigger?->value,
            'trigger_label' => $workflow->trigger?->label(),
            'action' => $workflow->action?->value,
            'action_label' => $workflow->action?->label(),
            'condition_tag_id' => $workflow->condition_tag_id,
            'tag_id' => $workflow->tag_id,
            'contact_status' => $workflow->contact_status,
            'webhook_url' => $workflow->webhook_url,
            'is_active' => (bool) $workflow->is_active,
            'run_count' => (int) $workflow->run_count,
            'last_run_at' => $workflow->last_run_at?->toDateTimeString(),
            'created_at' => $workflow->created_at?->toDateTimeString(),
        ];
    }
}
