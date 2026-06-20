<?php

namespace App\Modules\Booking\Traits;

use App\Modules\Booking\Enums\BookingStatus;

trait HasBookingSettings
{
    private const BOOKING_DEFAULTS = [
        'default_check_in_time' => '15:00',
        'default_check_out_time' => '11:00',
        'default_min_nights' => 1,
        'default_max_nights' => 30,
        'default_cancellation_policy' => 'flexible',
        'default_preparation_days' => 1,
        'default_advance_notice_days' => 1,
        'default_booking_status' => BookingStatus::Pending->value,
        'calendar_default_range' => 2,
        'calendar_max_range' => 12,
        'woocommerce_product_id' => 0,
        'auto_checkout_grace_hours' => 24,
        'auto_cancel_no_show_hours' => 48,
        'enable_auto_checkout' => true,
        'enable_no_show_auto_cancel' => true,
        'allow_guest_cancellation_statuses' => [BookingStatus::Pending->value, BookingStatus::Confirmed->value],
        'allow_guest_modification_statuses' => [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value],
        'grace_period_hours' => 24,
        'grace_period_advance_days' => 7,

        // System
        'system_damage_claim_window_days' => 30,
        'system_payout_hold_hours' => 24,
        'default_deposit_refund_days' => 7,

        // Email Notifications
        'email_enable_pre_arrival' => true,
        'email_pre_arrival_days' => 3,
        'email_enable_checkout_reminder' => true,
        'email_checkout_reminder_hours' => 24,
        'email_enable_post_stay' => true,
        'email_post_stay_delay_days' => 1,
        'email_enable_new_booking' => true,
        'email_enable_modification_request' => true,

        // Pricing Promotions
        'early_bird_discount_percent' => 0,
        'early_bird_days_before' => 30,
        'last_minute_discount_percent' => 0,
        'last_minute_days_within' => 7,
        'enable_nonrefundable_discount' => false,
        'nonrefundable_discount_percent' => 10,
    ];

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected static function getBookingSettingsFromOptions(array $options): array
    {
        return [
            'default_check_in_time' => $options['default_check_in_time'] ?? self::BOOKING_DEFAULTS['default_check_in_time'],
            'default_check_out_time' => $options['default_check_out_time'] ?? self::BOOKING_DEFAULTS['default_check_out_time'],
            'default_min_nights' => $options['default_min_nights'] ?? self::BOOKING_DEFAULTS['default_min_nights'],
            'default_max_nights' => $options['default_max_nights'] ?? self::BOOKING_DEFAULTS['default_max_nights'],
            'default_cancellation_policy' => $options['default_cancellation_policy'] ?? self::BOOKING_DEFAULTS['default_cancellation_policy'],
            'default_preparation_days' => $options['default_preparation_days'] ?? self::BOOKING_DEFAULTS['default_preparation_days'],
            'default_advance_notice_days' => $options['default_advance_notice_days'] ?? self::BOOKING_DEFAULTS['default_advance_notice_days'],
            'default_booking_status' => $options['default_booking_status'] ?? self::BOOKING_DEFAULTS['default_booking_status'],
            'calendar_default_range' => $options['calendar_default_range'] ?? self::BOOKING_DEFAULTS['calendar_default_range'],
            'calendar_max_range' => $options['calendar_max_range'] ?? self::BOOKING_DEFAULTS['calendar_max_range'],
            'woocommerce_product_id' => $options['woocommerce_product_id'] ?? self::BOOKING_DEFAULTS['woocommerce_product_id'],

            // Auto-Transition Controls
            'auto_checkout_grace_hours' => $options['auto_checkout_grace_hours'] ?? self::BOOKING_DEFAULTS['auto_checkout_grace_hours'],
            'auto_cancel_no_show_hours' => $options['auto_cancel_no_show_hours'] ?? self::BOOKING_DEFAULTS['auto_cancel_no_show_hours'],
            'enable_auto_checkout' => isset($options['enable_auto_checkout'])
                ? filter_var($options['enable_auto_checkout'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['enable_auto_checkout'],
            'enable_no_show_auto_cancel' => isset($options['enable_no_show_auto_cancel'])
                ? filter_var($options['enable_no_show_auto_cancel'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['enable_no_show_auto_cancel'],

            // Booking Workflow
            'allow_guest_cancellation_statuses' => $options['allow_guest_cancellation_statuses'] ?? self::BOOKING_DEFAULTS['allow_guest_cancellation_statuses'],
            'allow_guest_modification_statuses' => $options['allow_guest_modification_statuses'] ?? self::BOOKING_DEFAULTS['allow_guest_modification_statuses'],
            'grace_period_hours' => $options['grace_period_hours'] ?? self::BOOKING_DEFAULTS['grace_period_hours'],
            'grace_period_advance_days' => $options['grace_period_advance_days'] ?? self::BOOKING_DEFAULTS['grace_period_advance_days'],

            // System
            'system_damage_claim_window_days' => $options['system_damage_claim_window_days'] ?? self::BOOKING_DEFAULTS['system_damage_claim_window_days'],
            'system_payout_hold_hours' => $options['system_payout_hold_hours'] ?? self::BOOKING_DEFAULTS['system_payout_hold_hours'],
            'default_deposit_refund_days' => $options['default_deposit_refund_days'] ?? self::BOOKING_DEFAULTS['default_deposit_refund_days'],

            // Email Notifications
            'email_enable_pre_arrival' => isset($options['email_enable_pre_arrival'])
                ? filter_var($options['email_enable_pre_arrival'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['email_enable_pre_arrival'],
            'email_pre_arrival_days' => $options['email_pre_arrival_days'] ?? self::BOOKING_DEFAULTS['email_pre_arrival_days'],
            'email_enable_checkout_reminder' => isset($options['email_enable_checkout_reminder'])
                ? filter_var($options['email_enable_checkout_reminder'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['email_enable_checkout_reminder'],
            'email_checkout_reminder_hours' => $options['email_checkout_reminder_hours'] ?? self::BOOKING_DEFAULTS['email_checkout_reminder_hours'],
            'email_enable_post_stay' => isset($options['email_enable_post_stay'])
                ? filter_var($options['email_enable_post_stay'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['email_enable_post_stay'],
            'email_post_stay_delay_days' => $options['email_post_stay_delay_days'] ?? self::BOOKING_DEFAULTS['email_post_stay_delay_days'],
            'email_enable_new_booking' => isset($options['email_enable_new_booking'])
                ? filter_var($options['email_enable_new_booking'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['email_enable_new_booking'],
            'email_enable_modification_request' => isset($options['email_enable_modification_request'])
                ? filter_var($options['email_enable_modification_request'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['email_enable_modification_request'],

            // Pricing Promotions
            'early_bird_discount_percent' => $options['early_bird_discount_percent'] ?? self::BOOKING_DEFAULTS['early_bird_discount_percent'],
            'early_bird_days_before' => $options['early_bird_days_before'] ?? self::BOOKING_DEFAULTS['early_bird_days_before'],
            'last_minute_discount_percent' => $options['last_minute_discount_percent'] ?? self::BOOKING_DEFAULTS['last_minute_discount_percent'],
            'last_minute_days_within' => $options['last_minute_days_within'] ?? self::BOOKING_DEFAULTS['last_minute_days_within'],
            'enable_nonrefundable_discount' => isset($options['enable_nonrefundable_discount'])
                ? filter_var($options['enable_nonrefundable_discount'], FILTER_VALIDATE_BOOLEAN)
                : self::BOOKING_DEFAULTS['enable_nonrefundable_discount'],
            'nonrefundable_discount_percent' => $options['nonrefundable_discount_percent'] ?? self::BOOKING_DEFAULTS['nonrefundable_discount_percent'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function sanitizeBookingSettings(array $data): array
    {
        $sanitized = [];

        if (isset($data['default_check_in_time'])) {
            $sanitized['default_check_in_time'] = strip_tags(trim($data['default_check_in_time']));
        }

        if (isset($data['default_check_out_time'])) {
            $sanitized['default_check_out_time'] = strip_tags(trim($data['default_check_out_time']));
        }

        if (isset($data['default_min_nights'])) {
            $sanitized['default_min_nights'] = max(1, min(365, (int) $data['default_min_nights']));
        }

        if (isset($data['default_max_nights'])) {
            $sanitized['default_max_nights'] = max(1, min(365, (int) $data['default_max_nights']));
        }

        if (isset($data['default_cancellation_policy'])) {
            $allowed = ['flexible', 'moderate', 'limited', 'firm', 'strict', 'long_term', 'non_refundable'];
            $sanitized['default_cancellation_policy'] = in_array($data['default_cancellation_policy'], $allowed, true)
                ? $data['default_cancellation_policy']
                : 'flexible';
        }

        if (isset($data['default_preparation_days'])) {
            $sanitized['default_preparation_days'] = max(0, min(365, (int) $data['default_preparation_days']));
        }

        if (isset($data['default_advance_notice_days'])) {
            $sanitized['default_advance_notice_days'] = max(0, min(365, (int) $data['default_advance_notice_days']));
        }

        if (isset($data['default_booking_status'])) {
            $allowed = [BookingStatus::Pending->value, BookingStatus::Confirmed->value];
            $sanitized['default_booking_status'] = in_array($data['default_booking_status'], $allowed, true)
                ? $data['default_booking_status']
                : BookingStatus::Pending->value;
        }

        if (isset($data['calendar_default_range'])) {
            $sanitized['calendar_default_range'] = max(1, min(12, (int) $data['calendar_default_range']));
        }

        if (isset($data['calendar_max_range'])) {
            $sanitized['calendar_max_range'] = max(1, min(12, (int) $data['calendar_max_range']));
        }

        if (isset($data['woocommerce_product_id'])) {
            $sanitized['woocommerce_product_id'] = max(0, (int) $data['woocommerce_product_id']);
        }

        // Auto-Transition Controls
        if (isset($data['auto_checkout_grace_hours'])) {
            $sanitized['auto_checkout_grace_hours'] = max(0, min(168, (int) $data['auto_checkout_grace_hours']));
        }
        if (isset($data['auto_cancel_no_show_hours'])) {
            $sanitized['auto_cancel_no_show_hours'] = max(0, min(168, (int) $data['auto_cancel_no_show_hours']));
        }
        if (isset($data['enable_auto_checkout'])) {
            $sanitized['enable_auto_checkout'] = filter_var($data['enable_auto_checkout'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['enable_no_show_auto_cancel'])) {
            $sanitized['enable_no_show_auto_cancel'] = filter_var($data['enable_no_show_auto_cancel'], FILTER_VALIDATE_BOOLEAN);
        }

        // Booking Workflow
        if (isset($data['allow_guest_cancellation_statuses'])) {
            $allowedStatuses = [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value];
            $selected = is_array($data['allow_guest_cancellation_statuses'])
                ? $data['allow_guest_cancellation_statuses']
                : [];
            $sanitized['allow_guest_cancellation_statuses'] = array_values(array_intersect($selected, $allowedStatuses));
        }

        if (isset($data['allow_guest_modification_statuses'])) {
            $allowedStatuses = BookingStatus::values();
            $selected = is_array($data['allow_guest_modification_statuses'])
                ? $data['allow_guest_modification_statuses']
                : [];
            $sanitized['allow_guest_modification_statuses'] = array_values(array_intersect($selected, $allowedStatuses));
        }

        // Grace Period
        if (isset($data['grace_period_hours'])) {
            $sanitized['grace_period_hours'] = max(1, min(72, (int) $data['grace_period_hours']));
        }
        if (isset($data['grace_period_advance_days'])) {
            $sanitized['grace_period_advance_days'] = max(1, min(30, (int) $data['grace_period_advance_days']));
        }

        // System
        if (isset($data['system_damage_claim_window_days'])) {
            $sanitized['system_damage_claim_window_days'] = max(1, min(90, (int) $data['system_damage_claim_window_days']));
        }
        if (isset($data['system_payout_hold_hours'])) {
            $sanitized['system_payout_hold_hours'] = max(0, min(168, (int) $data['system_payout_hold_hours']));
        }
        if (isset($data['default_deposit_refund_days'])) {
            $sanitized['default_deposit_refund_days'] = max(1, min(90, (int) $data['default_deposit_refund_days']));
        }

        // Email Notifications
        if (isset($data['email_enable_pre_arrival'])) {
            $sanitized['email_enable_pre_arrival'] = filter_var($data['email_enable_pre_arrival'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['email_pre_arrival_days'])) {
            $sanitized['email_pre_arrival_days'] = max(0, min(30, (int) $data['email_pre_arrival_days']));
        }
        if (isset($data['email_enable_checkout_reminder'])) {
            $sanitized['email_enable_checkout_reminder'] = filter_var($data['email_enable_checkout_reminder'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['email_checkout_reminder_hours'])) {
            $sanitized['email_checkout_reminder_hours'] = max(1, min(72, (int) $data['email_checkout_reminder_hours']));
        }
        if (isset($data['email_enable_post_stay'])) {
            $sanitized['email_enable_post_stay'] = filter_var($data['email_enable_post_stay'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['email_post_stay_delay_days'])) {
            $sanitized['email_post_stay_delay_days'] = max(0, min(14, (int) $data['email_post_stay_delay_days']));
        }
        if (isset($data['email_enable_new_booking'])) {
            $sanitized['email_enable_new_booking'] = filter_var($data['email_enable_new_booking'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['email_enable_modification_request'])) {
            $sanitized['email_enable_modification_request'] = filter_var($data['email_enable_modification_request'], FILTER_VALIDATE_BOOLEAN);
        }

        // Pricing Promotions
        if (isset($data['early_bird_discount_percent'])) {
            $sanitized['early_bird_discount_percent'] = max(0, min(100, (int) $data['early_bird_discount_percent']));
        }
        if (isset($data['early_bird_days_before'])) {
            $sanitized['early_bird_days_before'] = max(1, min(365, (int) $data['early_bird_days_before']));
        }
        if (isset($data['last_minute_discount_percent'])) {
            $sanitized['last_minute_discount_percent'] = max(0, min(100, (int) $data['last_minute_discount_percent']));
        }
        if (isset($data['last_minute_days_within'])) {
            $sanitized['last_minute_days_within'] = max(1, min(90, (int) $data['last_minute_days_within']));
        }

        // Non-Refundable Discount
        if (isset($data['enable_nonrefundable_discount'])) {
            $sanitized['enable_nonrefundable_discount'] = filter_var($data['enable_nonrefundable_discount'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['nonrefundable_discount_percent'])) {
            $sanitized['nonrefundable_discount_percent'] = max(5, min(20, (int) $data['nonrefundable_discount_percent']));
        }

        return $sanitized;
    }
}
