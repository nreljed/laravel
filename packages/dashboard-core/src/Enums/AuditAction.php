<?php

namespace Nreljed\DashboardCore\Enums;

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
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeleted = 'role_deleted';
    case RoleDuplicated = 'role_duplicated';
    case RolePermissionChanged = 'role_permission_changed';
    case DatabaseReset = 'database_reset';
    case DatabaseReseed = 'database_reseed';

    public function severity(): string
    {
        return match ($this) {
            self::AccountDelete,
            self::ImpersonationStart,
            self::RoleRevoked,
            self::RoleDeleted,
            self::PermissionRevoked,
            self::DatabaseReset,
            self::DatabaseReseed => 'critical',

            self::PasswordChange,
            self::PasswordReset,
            self::PasswordResetSent,
            self::EmailChange,
            self::EmailVerified,
            self::RoleAssigned,
            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDuplicated,
            self::RolePermissionChanged,
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
