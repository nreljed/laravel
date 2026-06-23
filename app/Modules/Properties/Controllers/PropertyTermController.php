<?php

namespace App\Modules\Properties\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Properties\Models\PropertyCategory;
use App\Modules\Properties\Models\PropertyFeature;
use App\Modules\Properties\Models\PropertyStatus;
use App\Modules\Properties\Models\PropertyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyTermController extends Controller
{
    public function indexTypes(): Response
    {
        return Inertia::render('Properties/Types', [
            'types' => PropertyType::orderBy('sort_order')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PropertyType::create($validated);

        return redirect()->route('dashboard.properties.terms.types.index')
            ->with('status', 'Property type created.');
    }

    public function updateType(Request $request, PropertyType $type): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $type->update($validated);

        return redirect()->route('dashboard.properties.terms.types.index')
            ->with('status', 'Property type updated.');
    }

    public function destroyType(PropertyType $type): RedirectResponse
    {
        $type->delete();

        return redirect()->route('dashboard.properties.terms.types.index')
            ->with('status', 'Property type deleted.');
    }

    public function indexCategories(): Response
    {
        return Inertia::render('Properties/Categories', [
            'categories' => PropertyCategory::with('children')->whereNull('parent_id')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:property_categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PropertyCategory::create($validated);

        return redirect()->route('dashboard.properties.terms.categories.index')
            ->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, PropertyCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:property_categories,id|not_in:'.$category->id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return redirect()->route('dashboard.properties.terms.categories.index')
            ->with('status', 'Category updated.');
    }

    public function destroyCategory(PropertyCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('dashboard.properties.terms.categories.index')
            ->with('status', 'Category deleted.');
    }

    public function indexFeatures(): Response
    {
        return Inertia::render('Properties/Features', [
            'features' => PropertyFeature::orderBy('group')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeFeature(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'group' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PropertyFeature::create($validated);

        return redirect()->route('dashboard.properties.terms.features.index')
            ->with('status', 'Feature created.');
    }

    public function updateFeature(Request $request, PropertyFeature $feature): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'group' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $feature->update($validated);

        return redirect()->route('dashboard.properties.terms.features.index')
            ->with('status', 'Feature updated.');
    }

    public function destroyFeature(PropertyFeature $feature): RedirectResponse
    {
        $feature->delete();

        return redirect()->route('dashboard.properties.terms.features.index')
            ->with('status', 'Feature deleted.');
    }

    public function indexStatuses(): Response
    {
        return Inertia::render('Properties/Statuses', [
            'statuses' => PropertyStatus::orderBy('sort_order')->get(),
        ]);
    }

    public function storeStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        PropertyStatus::create($validated);

        return redirect()->route('dashboard.properties.terms.statuses.index')
            ->with('status', 'Property status created.');
    }

    public function updateStatus(Request $request, PropertyStatus $status): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $status->update($validated);

        return redirect()->route('dashboard.properties.terms.statuses.index')
            ->with('status', 'Property status updated.');
    }

    public function destroyStatus(PropertyStatus $status): RedirectResponse
    {
        $status->delete();

        return redirect()->route('dashboard.properties.terms.statuses.index')
            ->with('status', 'Property status deleted.');
    }
}
