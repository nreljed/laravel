<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\Platform\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LinkRedirectController extends Controller
{
    public function __invoke(Request $request, string $code, TrackedLinkService $links): RedirectResponse
    {
        $link = $links->findActive($code);

        if ($link === null) {
            throw new NotFoundHttpException('Tracked link not found.');
        }

        $destination = $links->destinationUrl($link);

        if ($destination === null) {
            throw new NotFoundHttpException('Tracked link target is invalid.');
        }

        $links->recordClick($link, $request);

        return new RedirectResponse($destination, 302, [
            'Cache-Control' => 'no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
