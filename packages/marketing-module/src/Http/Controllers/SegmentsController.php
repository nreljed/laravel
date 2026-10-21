<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Nreljed\MarketingModule\Models\Segment;
use Nreljed\MarketingModule\Requests\StoreSegmentRequest;
use Nreljed\MarketingModule\Resources\SegmentResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class SegmentsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Segment::class);

        $segments = Segment::query()
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (Segment $segment): array => $this->row($segment))
            ->all();

        return $renderer->renderIndex(SegmentResource::KEY, $segments);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Segment::class);

        return $renderer->renderForm(
            SegmentResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.segments.store'),
        );
    }

    public function store(StoreSegmentRequest $request)
    {
        $this->authorize('create', Segment::class);

        Segment::create($request->validated());

        return redirect()->back()->with('status', 'Segment created.');
    }

    public function edit(ResourceRenderer $renderer, Segment $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            SegmentResource::KEY,
            $this->row($record),
            'PUT',
            route('dashboard.marketing.segments.update', $record),
        );
    }

    public function update(StoreSegmentRequest $request, Segment $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Segment updated.');
    }

    public function destroy(Segment $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Segment deleted.');
    }

    /**
     * The row shape the generic index and form pages consume.
     *
     * @return array<string, mixed>
     */
    protected function row(Segment $segment): array
    {
        return [
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'conditions' => $segment->conditions,
            'is_active' => $segment->is_active,
            'contacts_count' => $segment->contacts_count ?? null,
            'created_at' => $segment->created_at?->toDateTimeString(),
        ];
    }
}
