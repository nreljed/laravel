<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Requests\StoreTagRequest;
use Nreljed\MarketingModule\Resources\TagResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class TagsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => $this->row($tag))
            ->all();

        return $renderer->renderIndex(TagResource::KEY, $tags);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Tag::class);

        return $renderer->renderForm(
            TagResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.tags.store'),
        );
    }

    public function store(StoreTagRequest $request)
    {
        $this->authorize('create', Tag::class);

        Tag::create($request->validated());

        return redirect()->back()->with('status', 'Tag created.');
    }

    public function edit(ResourceRenderer $renderer, Tag $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            TagResource::KEY,
            $this->row($record),
            'PUT',
            route('dashboard.marketing.tags.update', $record),
        );
    }

    public function update(StoreTagRequest $request, Tag $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Tag updated.');
    }

    public function destroy(Tag $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Tag deleted.');
    }

    /**
     * The row shape the generic index and form pages consume.
     *
     * @return array<string, mixed>
     */
    protected function row(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'contacts_count' => $tag->contacts_count ?? null,
            'created_at' => $tag->created_at?->toDateTimeString(),
        ];
    }
}
