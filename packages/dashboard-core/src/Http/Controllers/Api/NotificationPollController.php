<?php

namespace Nreljed\DashboardCore\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\Api\BulkNotificationRequest;
use Nreljed\DashboardCore\Http\Requests\Api\MarkReadRequest;
use Nreljed\DashboardCore\Models\Notification;
use Nreljed\DashboardCore\Services\NotificationService;
use Nreljed\DashboardCore\Traits\RelativeTime;

class NotificationPollController extends Controller
{
    use RelativeTime;

    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $userType = $request->user()::class;

        $count = $this->notificationService->getCachedUnreadCount($userId, $userType);

        return response()->json([
            'count' => $count,
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $userType = $user::class;

        $notifications = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Notification $n) => $this->format($n));

        $unreadCount = $this->notificationService->getCachedUnreadCount($userId, $userType);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $userType = $user::class;
        $limit = min((int) $request->input('limit', 20), 100);
        $offset = max((int) $request->input('offset', 0), 0);
        $type = $request->input('type', '');

        $query = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType);

        if ($type) {
            $query->where('type', $type);
        }

        $total = $query->count();
        $notifications = $query->latest()->skip($offset)->take($limit)->get()->map(fn (Notification $n) => $this->format($n));

        $unreadCount = $this->notificationService->getCachedUnreadCount($userId, $userType);

        return response()->json([
            'notifications' => $notifications,
            'total' => $total,
            'unread_count' => $unreadCount,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function markRead(MarkReadRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $userType = $request->user()::class;

        $updated = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->where('id', $request->id)
            ->update(['read_at' => now()]);

        if (! $updated) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $this->notificationService->invalidateUnreadCache($userId);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $request->user()::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->notificationService->invalidateUnreadCache($userId);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $deleted = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $request->user()::class)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $this->notificationService->invalidateUnreadCache($userId);

        return response()->json(['success' => true]);
    }

    public function markBulk(BulkNotificationRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $request->user()::class)
            ->whereIn('id', $request->ids)
            ->update(['read_at' => now()]);

        $this->notificationService->invalidateUnreadCache($userId);

        return response()->json(['success' => true]);
    }

    public function deleteBulk(BulkNotificationRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $request->user()::class)
            ->whereIn('id', $request->ids)
            ->delete();

        $this->notificationService->invalidateUnreadCache($userId);

        return response()->json(['success' => true]);
    }

    protected function format(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title ?? $n->data['title'] ?? '',
            'message' => $n->message ?? $n->data['message'] ?? $n->data['body'] ?? '',
            'link' => $n->link ?? $n->data['link'] ?? $n->data['url'] ?? '',
            'severity' => $n->severity ?? 'info',
            'module' => $n->module ?? 'core',
            'is_read' => (bool) $n->read_at,
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at->toISOString(),
            'created_at_relative' => self::relativeTime($n->created_at),
        ];
    }
}
