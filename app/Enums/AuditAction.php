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
    case BookingConfirmed = 'booking_confirmed';
    case BookingCheckedIn = 'booking_checked_in';
    case BookingCheckedOut = 'booking_checked_out';
    case BookingNoteAdded = 'booking_note_added';
    case ImpersonationStart = 'impersonation_start';
    case ImpersonationStop = 'impersonation_stop';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionAssigned = 'permission_assigned';
    case PermissionRevoked = 'permission_revoked';
}
