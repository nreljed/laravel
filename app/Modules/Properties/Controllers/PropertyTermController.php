<?php

namespace App\Modules\Properties\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Properties\Models\City;
use App\Modules\Properties\Models\Property;
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
        return Inertia::render('Modules/Properties/Types', [
            'types' => PropertyType::orderBy('sort_order')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:2048',
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
        $this->authorize('create', Property::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:2048',
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
        $this->authorize('create', Property::class);

        $type->delete();

        return redirect()->route('dashboard.properties.terms.types.index')
            ->with('status', 'Property type deleted.');
    }

    public function indexCities(): Response
    {
        return Inertia::render('Modules/Properties/Cities', [
            'cities' => City::orderBy('governorate_slug')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:2048',
            'governorate' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        City::create($validated);

        return redirect()->route('dashboard.properties.terms.cities.index')
            ->with('status', 'City created.');
    }

    public function updateCity(Request $request, City $city): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:2048',
            'governorate' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $city->update($validated);

        return redirect()->route('dashboard.properties.terms.cities.index')
            ->with('status', 'City updated.');
    }

    public function destroyCity(City $city): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $city->delete();

        return redirect()->route('dashboard.properties.terms.cities.index')
            ->with('status', 'City deleted.');
    }

    public function indexFeatures(): Response
    {
        return Inertia::render('Modules/Properties/Features', [
            'features' => PropertyFeature::orderBy('group')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeFeature(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

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
        $this->authorize('create', Property::class);

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
        $this->authorize('create', Property::class);

        $feature->delete();

        return redirect()->route('dashboard.properties.terms.features.index')
            ->with('status', 'Feature deleted.');
    }

    public function indexStatuses(): Response
    {
        return Inertia::render('Modules/Properties/Statuses', [
            'statuses' => PropertyStatus::orderBy('sort_order')->get(),
        ]);
    }

    public function storeStatus(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

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
        $this->authorize('create', Property::class);

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
        $this->authorize('create', Property::class);

        $status->delete();

        return redirect()->route('dashboard.properties.terms.statuses.index')
            ->with('status', 'Property status deleted.');
    }
}
