<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Services\SocialAuthService;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\Platform\Http\Controllers\Controller;
use Throwable;

/**
 * The OAuth handshake for connecting a social account.
 *
 * Both actions are deliberately quiet about provider errors: the underlying
 * exception often quotes a token response, and that text has no business on
 * a screen a user is looking at. The detail goes to the log; the screen gets
 * a sentence.
 */
class SocialAuthController extends Controller
{
    public function connect(
        Request $request,
        SocialAuthService $auth,
        SocialChannelRegistry $registry,
        string $platform,
    ) {
        $this->authorize('create', SocialAccount::class);

        abort_unless($registry->has($platform), 404);

        try {
            return redirect()->away($auth->redirectUrl($platform));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard.marketing.accounts.index')
                ->with('error', "Could not start the {$platform} connection. Check the app's credentials.");
        }
    }

    public function callback(Request $request, SocialAuthService $auth, string $platform)
    {
        $this->authorize('create', SocialAccount::class);

        try {
            $socialiteUser = $auth->provider($platform)->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard.marketing.accounts.index')
                ->with('error', 'The platform did not complete the connection.');
        }

        try {
            $auth->resolve($request->user(), $platform, $socialiteUser);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard.marketing.accounts.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.marketing.accounts.index')
            ->with('status', "The {$platform} account is connected.");
    }
}
