<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\BulkDeleteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkUserController extends Controller
{
    public function delete(BulkDeleteRequest $request): RedirectResponse
    {

        $currentUserId = $request->user()->id;
        $ids = array_filter($request->ids, fn ($id) => (int) $id !== $currentUserId);

        if (empty($ids)) {
            return redirect()->back()
                ->with('flash', ['error' => 'No users to delete (self-deletion excluded).']);
        }

        $count = User::whereIn('id', $ids)->count();
        User::whereIn('id', $ids)->delete();

        return redirect()->back()
            ->with('flash', ['success' => "{$count} user(s) deleted successfully."]);
    }

    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="users-'.now()->format('Y-m-d-His').'.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Username', 'Name', 'Email', 'Roles', 'Verified', 'Created At']);

            User::with('roles')->chunk(100, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->username,
                        $user->name,
                        $user->email,
                        $user->roles->pluck('slug')->implode(', '),
                        $user->hasVerifiedEmail() ? 'Yes' : 'No',
                        $user->created_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
