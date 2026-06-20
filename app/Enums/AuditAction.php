<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case PasswordChange = 'password_change';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';
    case AccountDelete = 'account_delete';
    case ProfileUpdate = 'profile_update';
    case BookingCreate = 'booking_create';
    case BookingUpdate = 'booking_update';
    case BookingCancel = 'booking_cancel';
    case BookingDelete = 'booking_delete';
    case BookingGuestRegister = 'booking_guest_register';
}
