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
    case ImpersonationStart = 'impersonation_start';
    case ImpersonationStop = 'impersonation_stop';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionAssigned = 'permission_assigned';
    case PermissionRevoked = 'permission_revoked';
}
