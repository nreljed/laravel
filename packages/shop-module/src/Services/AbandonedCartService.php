<?php

namespace Nreljed\ShopModule\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Nreljed\Platform\Services\MailService;
use Nreljed\ShopModule\Models\Cart;

class AbandonedCartService
{
    public function __construct(
        protected MailService $mailService,
    ) {}

    public function identifyAbandonedCarts(int $hoursSinceActivity = 1): Collection
    {
        $cutoff = now()->subHours($hoursSinceActivity);

        return Cart::whereHas('items')
            ->whereNull('converted_to_order_id')
            ->whereNull('abandoned_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->orWhere(function ($q) use ($cutoff) {
                $q->whereHas('items')
                    ->whereNull('converted_to_order_id')
                    ->whereNull('last_activity_at')
                    ->where('created_at', '<=', $cutoff);
            })
            ->get();
    }

    public function markAbandoned(Cart $cart): void
    {
        $cart->update(['abandoned_at' => now()]);
    }

    public function needsSequenceStep(Cart $cart, int $stepHours): bool
    {
        if (! $cart->abandoned_at) {
            return false;
        }

        $base = Carbon::parse($cart->abandoned_at);
        $threshold = $base->copy()->addHours($stepHours);

        return now()->gte($threshold);
    }

    public function sendRecoveryEmail(Cart $cart, int $sequenceStep): bool
    {
        $cart->load('items.product');
        if ($cart->items->isEmpty()) {
            return false;
        }

        $recipient = $cart->user?->email ?? $cart->billing_address['email'] ?? null;
        if (! $recipient) {
            return false;
        }

        $recoveryUrl = URL::signedRoute('store.cart.recover', ['cart' => $cart->id]);

        $template = match ($sequenceStep) {
            1 => 'cart-recovery-1h',
            2 => 'cart-recovery-24h',
            3 => 'cart-recovery-72h',
            default => 'cart-recovery-1h',
        };

        $subject = match ($sequenceStep) {
            1 => '['.config('app.name').'] You left items in your cart!',
            2 => '['.config('app.name').'] Still thinking about it?',
            3 => '['.config('app.name').'] Your cart is still waiting!',
            default => '['.config('app.name').'] You left items in your cart!',
        };

        $items = $cart->items->map(fn ($item) => [
            'name' => $item->product?->name ?? 'Product',
            'quantity' => $item->quantity,
            'price' => number_format((float) $item->unit_price, 2),
        ])->toArray();

        $this->mailService->send(
            $recipient,
            $subject,
            $template,
            [
                'customerName' => $cart->user?->name ?? 'Valued Customer',
                'cartUrl' => $recoveryUrl,
                'items' => $items,
                'itemCount' => count($items),
                'subtotal' => number_format((float) $cart->subtotal, 2).' '.($cart->currency ?? app(CurrencyService::class)->defaultCode()),
                'siteName' => config('app.name'),
            ],
        );

        $cart->update([
            'recovery_email_sent_at' => now(),
            'recovery_email_count' => $cart->recovery_email_count + 1,
        ]);

        return true;
    }

    public function handleRecovery(Cart $cart): string
    {
        $cart->update([
            'recovered_at' => now(),
            'abandoned_at' => null,
        ]);

        if ($cart->user_id) {
            return route('dashboard.shop.cart.index');
        }

        return route('checkout.index');
    }

    public function getRecoveryStats(): array
    {
        $totalAbandoned = Cart::whereNotNull('abandoned_at')->count();
        $totalRecovered = Cart::whereNotNull('recovered_at')->count();
        $totalEmailsSent = Cart::sum('recovery_email_count');

        return [
            'total_abandoned' => $totalAbandoned,
            'total_recovered' => $totalRecovered,
            'recovery_rate' => $totalAbandoned > 0
                ? round(($totalRecovered / $totalAbandoned) * 100, 1)
                : 0,
            'total_recovery_emails_sent' => $totalEmailsSent,
        ];
    }
}
