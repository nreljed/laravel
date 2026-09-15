<?php

namespace Nreljed\BookingModule\Properties\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\BookingModule\Properties\Models\City;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\BookingModule\Properties\Models\PropertyFeature;
use Nreljed\BookingModule\Properties\Models\PropertyStatus;
use Nreljed\BookingModule\Properties\Models\PropertyType;
use Nreljed\Platform\Http\Controllers\Controller;

class PropertyTermController extends Controller
{
    protected function authorizeTermMutation(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $user->hasPermission($permission)), 403);
    }

    public function indexTypes(): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('properties/types', [
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
        $this->authorizeTermMutation('properties.edit');

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
        $this->authorizeTermMutation('properties.delete');

        $type->delete();

        return redirect()->route('dashboard.properties.terms.types.index')
            ->with('status', 'Property type deleted.');
    }

    public function indexCities(): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('properties/cities', [
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
        $this->authorizeTermMutation('properties.edit');

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
        $this->authorizeTermMutation('properties.delete');

        $city->delete();

        return redirect()->route('dashboard.properties.terms.cities.index')
            ->with('status', 'City deleted.');
    }

    public function indexFeatures(): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('properties/features', [
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
        $this->authorizeTermMutation('properties.edit');

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
        $this->authorizeTermMutation('properties.delete');

        $feature->delete();

        return redirect()->route('dashboard.properties.terms.features.index')
            ->with('status', 'Feature deleted.');
    }

    public function indexStatuses(): Response
    {
        $this->authorize('viewAny', Property::class);

        return Inertia::render('properties/statuses', [
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
        $this->authorizeTermMutation('properties.edit');

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
        $this->authorizeTermMutation('properties.delete');

        $status->delete();

        return redirect()->route('dashboard.properties.terms.statuses.index')
            ->with('status', 'Property status deleted.');
    }
}
