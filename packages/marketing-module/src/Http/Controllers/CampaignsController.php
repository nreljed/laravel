<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Requests\StoreCampaignRequest;
use Nreljed\MarketingModule\Resources\CampaignResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class CampaignsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->withCount('posts')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Campaign $campaign): array => $this->row($campaign))
            ->all();

        return $renderer->renderIndex(CampaignResource::KEY, $campaigns);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Campaign::class);

        return $renderer->renderForm(
            CampaignResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.campaigns.store'),
        );
    }

    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        Campaign::create($request->validated());

        return redirect()
            ->route('dashboard.marketing.campaigns.index')
            ->with('status', 'Campaign created.');
    }

    public function edit(ResourceRenderer $renderer, Campaign $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            CampaignResource::KEY,
            $this->row($record),
            'PUT',
            route('dashboard.marketing.campaigns.update', $record),
        );
    }

    public function update(StoreCampaignRequest $request, Campaign $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()
            ->route('dashboard.marketing.campaigns.index')
            ->with('status', 'Campaign updated.');
    }

    public function destroy(Campaign $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Campaign deleted. Its posts were kept.');
    }

    /**
     * Campaigns the composer may attach a post to.
     */
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        return response()->json(
            Campaign::query()
                ->orderBy('name')
                ->get()
                ->map(static fn (Campaign $campaign): array => [
                    'value' => $campaign->id,
                    'label' => $campaign->name,
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'objective' => $campaign->objective?->value,
            'objective_label' => $campaign->objective?->label(),
            'status' => $campaign->status?->value,
            'status_label' => $campaign->status?->label(),
            'posts_count' => $campaign->posts_count ?? $campaign->posts()->count(),
            'starts_at' => $campaign->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $campaign->ends_at?->format('Y-m-d\TH:i'),
            'created_at' => $campaign->created_at?->toDateTimeString(),
        ];
    }
}
