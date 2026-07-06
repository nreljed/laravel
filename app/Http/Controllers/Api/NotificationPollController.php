<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkReadRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPollController extends Controller
{
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $userType = get_class($user);

        $meta = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereNull('read_at')
            ->selectRaw('COUNT(*) as count, COALESCE(MAX(id), 0) as latest_id, COALESCE(MAX(created_at), \'\') as latest_at')
            ->first();

        return response()->json([
            'count' => (int) $meta->count,
            'latest_id' => (string) $meta->latest_id,
            'latest_at' => (string) $meta->latest_at,
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $userType = get_class($user);

        $unreadCount = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereNull('read_at')
            ->count();

        $notifications = Notification::where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Notification $n) => $this->format($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->input('limit', 20), 100);
        $offset = max((int) $request->input('offset', 0), 0);
        $type = $request->input('type', '');

        $query = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user));

        if ($type) {
            $query->where('type', 'like', "%{$type}%");
        }

        $total = $query->count();
        $notifications = $query->latest()->skip($offset)->take($limit)->get()->map(fn (Notification $n) => $this->format($n));

        $unreadCount = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->count();

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
        $updated = Notification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->where('id', $request->id)
            ->update(['read_at' => now()]);

        if (! $updated) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = Notification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function markBulk(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['error' => 'No IDs provided'], 400);
        }

        Notification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->whereIn('id', $ids)
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function deleteBulk(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['error' => 'No IDs provided'], 400);
        }

        Notification::where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->whereIn('id', $ids)
            ->delete();

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
            'is_read' => $n->read_at ? '1' : '0',
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at->toISOString(),
            'created_at_relative' => $this->relativeTime($n->created_at),
        ];
    }

    protected function relativeTime(\DateTimeInterface $datetime): string
    {
        $diff = time() - $datetime->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60).'m ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600).'h ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400).'d ago';
        }

        return $datetime->format('M j');
    }
}
