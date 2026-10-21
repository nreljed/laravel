<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Requests\StoreSocialAccountRequest;
use Nreljed\MarketingModule\Resources\SocialAccountResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

/**
 * The connected social accounts.
 *
 * There is no create action: an account is created by completing an OAuth
 * handshake, not by filling in a form. The form therefore only edits what a
 * user owns after the handshake — the display name, the handle, and the
 * Pinterest board new pins go to.
 */
class SocialAccountsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', SocialAccount::class);

        $accounts = SocialAccount::query()
            ->where('user_id', auth()->id())
            ->orderBy('platform')
            ->orderBy('account_name')
            ->get()
            ->map(fn (SocialAccount $account): array => $this->row($account))
            ->all();

        return $renderer->renderIndex(SocialAccountResource::KEY, $accounts);
    }

    public function edit(ResourceRenderer $renderer, SocialAccount $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            SocialAccountResource::KEY,
            $this->row($record),
            'PUT',
            route('dashboard.marketing.accounts.update', $record),
        );
    }

    public function update(StoreSocialAccountRequest $request, SocialAccount $record)
    {
        $this->authorize('update', $record);

        $data = $request->validated();

        // The board is not a column: it lives in the account's metadata so
        // the schema stays the same for every platform.
        if (array_key_exists('board_id', $data)) {
            $boardId = $data['board_id'];
            unset($data['board_id']);

            $metadata = (array) $record->metadata;

            if ($boardId === null || $boardId === '') {
                unset($metadata['default_board']);
            } else {
                $metadata['default_board'] = $boardId;
            }

            $data['metadata'] = $metadata;
        }

        $record->update($data);

        return redirect()->back()->with('status', 'Account updated.');
    }

    public function destroy(SocialAccount $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Account disconnected.');
    }

    /**
     * The row shape the generic index and form pages consume.
     *
     * @return array<string, mixed>
     */
    protected function row(SocialAccount $account): array
    {
        return [
            'id' => $account->id,
            'account_name' => $account->account_name,
            'platform' => $account->platform,
            'username' => $account->username,
            'status' => $account->status?->value,
            'board_id' => $account->boardId(),
            'last_validated_at' => $account->last_validated_at?->toDateTimeString(),
        ];
    }
}
