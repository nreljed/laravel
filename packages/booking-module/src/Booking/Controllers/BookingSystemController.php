<?php

namespace Nreljed\BookingModule\Booking\Controllers;

use Illuminate\Http\JsonResponse;
use Nreljed\BookingModule\Booking\Services\BookingStatusManager;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\SettingsService;

class BookingSystemController extends Controller
{
    public function __construct(
        protected BookingStatusManager $statusManager,
    ) {}

    public function runTransitions(): JsonResponse
    {
        if (! auth()->user()->can('manage_options')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->statusManager->processAutoTransitions();

        $message = sprintf(
            'Auto-transitions completed: %d checked out, %d no-show cancelled.',
            $result['checked_out'],
            $result['no_show']
        );

        if ($result['checked_out'] === 0 && $result['no_show'] === 0) {
            $message = 'No bookings required auto-transitions at this time.';
        }

        return response()->json([
            'success' => true,
            'checked_out' => $result['checked_out'],
            'cancelled_no_show' => $result['no_show'],
            'message' => $message,
            'cron_last_run' => SettingsService::get('booking_cron_last_run', 'never'),
        ]);
    }

    public function status(): JsonResponse
    {
        if (! auth()->user()->can('manage_options')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $settings = SettingsService::getGroup('booking');

        return response()->json([
            'cron_scheduled' => true,
            'cron_next_run' => null,
            'cron_last_run' => SettingsService::get('booking_cron_last_run', 'never'),
            'enable_auto_checkout' => ! empty($settings['enable_auto_checkout']),
            'enable_no_show_auto_cancel' => ! empty($settings['enable_no_show_auto_cancel']),
        ]);
    }
}
