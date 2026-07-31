<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case PasswordChange = 'password_change';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';
    case AccountCreated = 'account_created';
    case AccountDelete = 'account_delete';
    case EmailVerified = 'email_verified';
    case PasswordResetSent = 'password_reset_sent';
    case SettingsChanged = 'settings_changed';
    case ProfileUpdate = 'profile_update';
    case ImpersonationStart = 'impersonation_start';
    case ImpersonationStop = 'impersonation_stop';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionAssigned = 'permission_assigned';
    case PermissionRevoked = 'permission_revoked';

    public function severity(): string
    {
        return match ($this) {
            self::AccountDelete,
            self::ImpersonationStart,
            self::RoleRevoked,
            self::PermissionRevoked => 'critical',

            self::PasswordChange,
            self::PasswordReset,
            self::PasswordResetSent,
            self::EmailChange,
            self::EmailVerified,
            self::RoleAssigned,
            self::PermissionAssigned,
            self::SettingsChanged,
            self::ProfileUpdate,
            self::AccountCreated => 'warning',

            self::Login,
            self::Logout,
            self::ImpersonationStop => 'info',
        };
    }
}
