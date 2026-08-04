<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Models\AuditLog;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Services\SettingsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user.roles');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('severity')) {
            $severityActions = collect(AuditAction::cases())
                ->filter(fn ($case) => $case->severity() === $request->severity)
                ->map(fn ($case) => $case->value)
                ->values()
                ->toArray();
            $query->whereIn('action', $severityActions);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $userIds = User::where('name', 'like', "%{$search}%")->pluck('id');
            $query->where(function ($q) use ($search, $userIds) {
                $q->whereIn('user_id', $userIds)
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('context', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $defaultPerPage = (int) SettingsService::get('audit_logs.default_per_page', 50);
        $perPage = min((int) $request->input('per_page', $defaultPerPage), 100);
        $logs = $query->latest('id')->paginate($perPage);

        $targetIds = collect($logs->items())
            ->map(fn ($log) => $log->context['target_user_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $targetUsers = User::whereIn('id', $targetIds)->with('roles')->get()->keyBy('id');

        $logs->through(function ($log) use ($targetUsers) {
            $target = null;
            $tid = $log->context['target_user_id'] ?? null;

            if ($tid) {
                $existing = $targetUsers->get($tid);
                if ($existing) {
                    $target = [
                        'id' => $existing->id,
                        'name' => $existing->name,
                        'email' => $existing->email,
                        'username' => $existing->username,
                        'display_name' => $existing->display_name,
                        'deleted' => false,
                        'roles' => $existing->roles->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name]),
                    ];
                } elseif ($log->context && ($log->context['target_name'] ?? null)) {
                    $target = [
                        'id' => $tid,
                        'name' => $log->context['target_name'],
                        'email' => $log->context['target_email'] ?? null,
                        'username' => $log->context['target_username'] ?? null,
                        'display_name' => $log->context['target_display_name'] ?? null,
                        'deleted' => true,
                    ];
                }
            }

            $actionEnum = AuditAction::tryFrom($log->action);

            return [
                'id' => $log->id,
                'action' => $log->action,
                'severity' => $actionEnum?->severity() ?? 'info',
                'description' => $this->describe($log, $target),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'username' => $log->user->username,
                    'display_name' => $log->user->display_name,
                    'roles' => $log->user->roles->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name]),
                ] : null,
                'target' => $target,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'context' => $log->context,
                'created_at' => $log->created_at->toISOString(),
            ];
        });

        $actions = AuditLog::distinct()->pluck('action')->sort()->values();
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'username', 'display_name']);

        $today = now()->startOfDay();
        $stats = [
            'total' => AuditLog::count(),
            'today' => AuditLog::where('created_at', '>=', $today)->count(),
            'critical' => AuditLog::whereIn('action',
                collect(AuditAction::cases())
                    ->filter(fn ($c) => $c->severity() === 'critical')
                    ->map(fn ($c) => $c->value)
                    ->toArray()
            )->count(),
        ];

        return Inertia::render('dashboard/admin/audit-logs/index', [
            'logs' => $logs,
            'actions' => $actions,
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['action', 'severity', 'user_id', 'search', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('severity')) {
            $severityActions = collect(AuditAction::cases())
                ->filter(fn ($case) => $case->severity() === $request->severity)
                ->map(fn ($case) => $case->value)
                ->values()
                ->toArray();
            $query->whereIn('action', $severityActions);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $userIds = User::where('name', 'like', "%{$search}%")->pluck('id');
            $query->where(function ($q) use ($search, $userIds) {
                $q->whereIn('user_id', $userIds)
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('context', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-'.now()->format('Y-m-d-His').'.csv"',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Date', 'Time', 'Severity', 'Actor', 'Actor Email', 'Actor Roles', 'Action',
                'Target Name', 'Target Email', 'Target Roles', 'IP Address', 'User Agent', 'Context',
            ],
                escape: '\\');

            $query->chunk(200, function ($logs) use ($handle) {
                $targetIds = collect($logs)
                    ->map(fn ($log) => $log->context['target_user_id'] ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                $targetUsers = User::whereIn('id', $targetIds)->get()->keyBy('id');

                foreach ($logs as $log) {
                    $tid = $log->context['target_user_id'] ?? null;
                    $targetName = null;
                    $targetEmail = null;
                    $targetRoles = '';
                    $existing = null;

                    if ($tid) {
                        $existing = $targetUsers->get($tid);
                        if ($existing) {
                            $targetName = $existing->name;
                            $targetEmail = $existing->email;
                            $targetRoles = $existing->roles->pluck('slug')->implode(', ');
                        } else {
                            $targetName = $log->context['target_name'] ?? null;
                            $targetEmail = $log->context['target_email'] ?? null;
                        }
                    }

                    $actorRoles = $log->user !== null
                        ? $log->user->roles->pluck('slug')->implode(', ')
                        : '';
                    $actionEnum = AuditAction::tryFrom($log->action);

                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->toDateString(),
                        $log->created_at->toTimeString('minute'),
                        $actionEnum?->severity() ?? 'info',
                        $log->user->name ?? 'System',
                        $log->user->email ?? '',
                        $actorRoles,
                        $log->action,
                        $targetName ?? '',
                        $targetEmail ?? '',
                        $targetRoles,
                        $log->ip ?? '',
                        $log->user_agent ?? '',
                        $log->context ? json_encode($log->context) : '',
                    ],
                        escape: '\\');
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function describe(AuditLog $log, ?array $target): string
    {
        $actor = $log->user->name ?? 'System';
        $action = $log->action;
        $ctx = $log->context ?? [];
        $targetName = $target['name'] ?? null;
        $targetUsername = $target['username'] ?? null;
        $targetLabel = $targetName
            ? $targetName.($targetUsername ? " (@$targetUsername)" : '')
            : null;

        return match ($action) {
            AuditAction::Login->value => "$actor logged in",
            AuditAction::Logout->value => "$actor logged out",
            AuditAction::PasswordChange->value => "$actor changed their password",
            AuditAction::PasswordReset->value => "$actor reset their password",
            AuditAction::PasswordResetSent->value => $targetLabel
                ? "$actor sent password reset link to $targetLabel"
                : "$actor sent a password reset link",
            AuditAction::EmailChange->value => "$actor changed their email",
            AuditAction::EmailVerified->value => $targetLabel
                ? "$actor verified email for $targetLabel"
                : "$actor verified an email",
            AuditAction::AccountCreated->value => $targetLabel
                ? "$actor created user $targetLabel"
                : "$actor created a new user",
            AuditAction::AccountDelete->value => $targetLabel
                ? "$actor deleted user $targetLabel"
                : "$actor deleted their account",
            AuditAction::ProfileUpdate->value => "$actor updated their profile",
            AuditAction::ImpersonationStart->value => $targetLabel
                ? "$actor impersonated $targetLabel"
                : "$actor started impersonation",
            AuditAction::ImpersonationStop->value => "$actor stopped impersonation",
            AuditAction::RoleAssigned->value => $targetLabel
                ? "$actor assigned role ".($this->describeRoleSlugs($ctx) ?? $ctx['role_slug'] ?? $ctx['role'] ?? 'unknown')." to $targetLabel"
                : "$actor assigned role ".($this->describeRoleSlugs($ctx) ?? $ctx['role'] ?? $ctx['role_slug'] ?? 'unknown'),
            AuditAction::RoleRevoked->value => $targetLabel
                ? "$actor revoked role ".($this->describeRoleSlugs($ctx) ?? $ctx['role_slug'] ?? $ctx['role'] ?? 'unknown')." from $targetLabel"
                : "$actor revoked role ".($this->describeRoleSlugs($ctx) ?? $ctx['role'] ?? $ctx['role_slug'] ?? 'unknown'),
            AuditAction::PermissionAssigned->value => "$actor assigned permissions to role {$ctx['role']}",
            AuditAction::PermissionRevoked->value => "$actor revoked permissions from role {$ctx['role']}",
            AuditAction::RoleCreated->value => "$actor created role {$ctx['role']}",
            AuditAction::RoleUpdated->value => "$actor updated role {$ctx['role']}",
            AuditAction::RoleDeleted->value => "$actor deleted role {$ctx['role']}",
            AuditAction::RoleDuplicated->value => "$actor duplicated role {$ctx['source_role']} as {$ctx['role']}",
            AuditAction::RolePermissionChanged->value => "$actor changed permissions on role {$ctx['role']}",
            AuditAction::SettingsChanged->value => "$actor changed app settings",
            AuditAction::DatabaseReset->value => $ctx['purge_media']
                ? "$actor reset the database and reseeded it (media purge enabled)"
                : "$actor reset the database and reseeded it",
            AuditAction::DatabaseReseed->value => "$actor re-ran the seeders (no data wiped)",
            default => "$actor performed $action",
        };
    }

    private function describeRoleSlugs(array $ctx): ?string
    {
        $slugs = $ctx['role_slugs'] ?? null;

        if (is_array($slugs) && $slugs !== []) {
            return implode(', ', array_map('strval', $slugs));
        }

        return null;
    }
}
