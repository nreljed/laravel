<?php

namespace Nreljed\BookingModule\Booking\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Nreljed\BookingModule\Booking\Services\BookingSettingsService;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Http\Controllers\Controller;

class BookingGlobalSettingsController extends Controller
{
    public function __construct(private readonly BookingSettingsService $settings) {}

    public function isModuleEnabled(): bool
    {
        return true;
    }

    /**
     * All booking settings merged against defaults (typed).
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $merged = $this->settings->all();

        $tz = config('app.timezone', 'UTC');
        $now = new \DateTime('now', new \DateTimeZone($tz));
        $offset = $now->getOffset();
        $sign = $offset >= 0 ? '+' : '-';
        $hours = sprintf('%02d', (int) (abs($offset) / 3600));
        $minutes = sprintf('%02d', (int) ((abs($offset) % 3600) / 60));
        $offsetStr = "UTC{$sign}{$hours}:{$minutes}";

        $merged['_site_timezone'] = $tz;
        $merged['_site_timezone_label'] = "{$tz} ({$offsetStr})";

        return $merged;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    public function getCurrency(?int $propertyId = null): string
    {
        if ($propertyId) {
            $property = Property::find($propertyId);
            if ($property && ! empty($property->getMeta()['currency'])) {
                return strtoupper($property->getMeta()['currency']);
            }
        }
        $currency = $this->settings->get('default_currency');
        if (! empty($currency)) {
            return strtoupper((string) $currency);
        }

        return 'USD';
    }

    public function index(): mixed
    {
        abort_unless($this->isModuleEnabled(), 404);

        if (! auth()->user()->can('manage_options')) {
            abort(403);
        }

        return Inertia::render('bookings/settings/index', [
            'settings' => $this->getOptions(),
        ]);
    }

    public function update(Request $request): mixed
    {
        if (! auth()->user()->can('manage_options')) {
            abort(403);
        }

        try {
            $this->settings->update($request->all());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('dashboard.bookings.settings.index')
            ->with('status', 'Booking settings saved.');
    }
}
