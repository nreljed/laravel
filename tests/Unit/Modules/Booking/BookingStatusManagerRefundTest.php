<?php

namespace Tests\Unit\Modules\Booking;

use App\Modules\Booking\Services\BookingStatusManager;
use Tests\TestCase;

class BookingStatusManagerRefundTest extends TestCase
{
    private BookingStatusManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new BookingStatusManager;
    }

    private function makeCheckIn(string $daysFromNow = '+7 days'): string
    {
        return now()->modify($daysFromNow)->format('Y-m-d');
    }

    private function makeCheckInPast(): string
    {
        return now()->subDays(1)->format('Y-m-d');
    }

    public function test_flexible_policy_full_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'flexible',
            $this->makeCheckIn('+3 days'),
            null,
            1000,
            ['full_refund_days' => 1]
        );

        $this->assertEquals(100, $result['refund_percent']);
        $this->assertEquals(1000, $result['refund_amount']);
        $this->assertEquals('flexible', $result['policy_applied']);
    }

    public function test_flexible_policy_partial_refund_within_window(): void
    {
        $result = $this->manager->calculateRefund(
            'flexible',
            $this->makeCheckIn('+12 hours'),
            null,
            1000,
            ['full_refund_days' => 1, 'partial_refund_cutoff_days' => 0, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(50, $result['refund_percent']);
        $this->assertEquals(500, $result['refund_amount']);
    }

    public function test_moderate_policy_full_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'moderate',
            $this->makeCheckIn('+7 days'),
            null,
            2000,
            ['full_refund_days' => 5]
        );

        $this->assertEquals(100, $result['refund_percent']);
        $this->assertEquals(2000, $result['refund_amount']);
    }

    public function test_moderate_policy_partial_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'moderate',
            $this->makeCheckIn('+2 days'),
            null,
            2000,
            ['full_refund_days' => 5, 'partial_refund_cutoff_days' => 1, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(50, $result['refund_percent']);
    }

    public function test_moderate_policy_no_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'moderate',
            $this->makeCheckIn('+12 hours'),
            null,
            2000,
            ['full_refund_days' => 5, 'partial_refund_cutoff_days' => 1]
        );

        $this->assertEquals(0, $result['refund_percent']);
        $this->assertEquals(0, $result['refund_amount']);
    }

    public function test_firm_policy_full_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'firm',
            $this->makeCheckIn('+31 days'),
            null,
            1500,
            ['full_refund_days' => 30, 'partial_refund_cutoff_days' => 7, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(100, $result['refund_percent']);
    }

    public function test_firm_policy_partial_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'firm',
            $this->makeCheckIn('+14 days'),
            null,
            1500,
            ['full_refund_days' => 30, 'partial_refund_cutoff_days' => 7, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(50, $result['refund_percent']);
    }

    public function test_firm_policy_no_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'firm',
            $this->makeCheckIn('+3 days'),
            null,
            1500,
            ['full_refund_days' => 30, 'partial_refund_cutoff_days' => 7]
        );

        $this->assertEquals(0, $result['refund_percent']);
    }

    public function test_strict_policy_partial_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'strict',
            $this->makeCheckIn('+10 days'),
            null,
            1200,
            ['full_refund_days' => 7, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(50, $result['refund_percent']);
        $this->assertEquals(600, $result['refund_amount']);
    }

    public function test_strict_policy_no_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'strict',
            $this->makeCheckIn('+3 days'),
            null,
            1200,
            ['full_refund_days' => 7]
        );

        $this->assertEquals(0, $result['refund_percent']);
    }

    public function test_non_refundable_policy(): void
    {
        $result = $this->manager->calculateRefund(
            'non_refundable',
            $this->makeCheckIn('+30 days'),
            null,
            500
        );

        $this->assertEquals(0, $result['refund_percent']);
        $this->assertEquals(0, $result['refund_amount']);
    }

    public function test_long_term_policy_full_refund_for_short_bookings(): void
    {
        $result = $this->manager->calculateRefund(
            'long_term',
            $this->makeCheckIn('+7 days'),
            null,
            3000,
            ['total_nights' => 14, 'long_term_min_nights' => 28]
        );

        $this->assertEquals(100, $result['refund_percent']);
    }

    public function test_long_term_policy_partial_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'long_term',
            $this->makeCheckIn('+7 days'),
            null,
            10000,
            ['total_nights' => 60, 'long_term_min_nights' => 28, 'long_term_nonrefundable_nights' => 30, 'full_refund_days' => 30]
        );

        $this->assertGreaterThan(0, $result['refund_percent']);
        $this->assertLessThan(100, $result['refund_percent']);
    }

    public function test_grace_period_full_refund(): void
    {
        $bookedAt = now()->subHours(12)->toDateTimeString();

        $result = $this->manager->calculateRefund(
            'strict',
            $this->makeCheckIn('+14 days'),
            null,
            1000,
            [
                'booked_at' => $bookedAt,
                'grace_period_hours' => 24,
                'grace_period_advance_days' => 7,
            ]
        );

        $this->assertEquals(100, $result['refund_percent']);
        $this->assertEquals('grace_period', $result['policy_applied']);
    }

    public function test_grace_period_not_applied_when_checkin_too_soon(): void
    {
        $bookedAt = now()->subHours(12)->toDateTimeString();

        $result = $this->manager->calculateRefund(
            'strict',
            $this->makeCheckIn('+3 days'),
            null,
            1000,
            [
                'booked_at' => $bookedAt,
                'grace_period_hours' => 24,
                'grace_period_advance_days' => 7,
            ]
        );

        $this->assertEquals(0, $result['refund_percent']);
        $this->assertNotEquals('grace_period', $result['policy_applied']);
    }

    public function test_grace_period_not_applied_past_24h(): void
    {
        $bookedAt = now()->subHours(48)->toDateTimeString();

        $result = $this->manager->calculateRefund(
            'flexible',
            $this->makeCheckIn('+14 days'),
            null,
            1000,
            [
                'booked_at' => $bookedAt,
                'grace_period_hours' => 24,
                'grace_period_advance_days' => 7,
            ]
        );

        $this->assertNotEquals('grace_period', $result['policy_applied']);
    }

    public function test_mid_trip_flexible_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'flexible',
            now()->subDays(3)->format('Y-m-d'),
            null,
            2000,
            [
                'total_nights' => 10,
                'is_mid_trip' => true,
                'nights_stayed' => 4,
            ]
        );

        $this->assertGreaterThan(0, $result['refund_percent']);
        $this->assertTrue($result['refund_amount'] > 0);
    }

    public function test_mid_trip_moderate_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'moderate',
            now()->subDays(3)->format('Y-m-d'),
            null,
            2000,
            [
                'total_nights' => 10,
                'is_mid_trip' => true,
                'nights_stayed' => 4,
            ]
        );

        $this->assertGreaterThan(0, $result['refund_percent']);
    }

    public function test_limited_policy_full_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'limited',
            $this->makeCheckIn('+20 days'),
            null,
            800,
            ['full_refund_days' => 14, 'partial_refund_cutoff_days' => 7, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(100, $result['refund_percent']);
    }

    public function test_limited_policy_partial_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'limited',
            $this->makeCheckIn('+10 days'),
            null,
            800,
            ['full_refund_days' => 14, 'partial_refund_cutoff_days' => 7, 'partial_refund_percent' => 50]
        );

        $this->assertEquals(50, $result['refund_percent']);
    }

    public function test_limited_policy_no_refund(): void
    {
        $result = $this->manager->calculateRefund(
            'limited',
            $this->makeCheckIn('+3 days'),
            null,
            800,
            ['full_refund_days' => 14, 'partial_refund_cutoff_days' => 7]
        );

        $this->assertEquals(0, $result['refund_percent']);
    }

    public function test_is_within_grace_period(): void
    {
        $result = $this->manager->isWithinGracePeriod(
            now()->subHours(12)->toDateTimeString(),
            $this->makeCheckIn('+14 days'),
            null,
            24,
            7
        );

        $this->assertTrue($result);
    }

    public function test_is_not_within_grace_period_when_too_late(): void
    {
        $result = $this->manager->isWithinGracePeriod(
            now()->subHours(48)->toDateTimeString(),
            $this->makeCheckIn('+14 days'),
            null,
            24,
            7
        );

        $this->assertFalse($result);
    }

    public function test_is_not_within_grace_period_when_checkin_too_soon(): void
    {
        $result = $this->manager->isWithinGracePeriod(
            now()->subHours(12)->toDateTimeString(),
            $this->makeCheckIn('+3 days'),
            null,
            24,
            7
        );

        $this->assertFalse($result);
    }
}
