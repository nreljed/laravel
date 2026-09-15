<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Services\AbandonedCartService;

class ProcessAbandonedCarts extends Command
{
    protected $signature = 'shop:abandoned-cart-process';

    protected $description = 'Identify abandoned carts and send recovery email sequences';

    public function __construct(
        protected AbandonedCartService $abandonedCartService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $config = config('shop.abandoned_cart', [
            'hours_until_abandoned' => 1,
            'sequence_hours' => [1, 24, 72],
        ]);

        $sequenceHours = $config['sequence_hours'];

        $newlyAbandoned = $this->abandonedCartService->identifyAbandonedCarts(
            $config['hours_until_abandoned'],
        );

        foreach ($newlyAbandoned as $cart) {
            $this->abandonedCartService->markAbandoned($cart);
            $this->line("Marked cart #{$cart->id} as abandoned.");
        }

        foreach ($sequenceHours as $step => $hours) {
            $sequenceStep = $step + 1;

            $carts = Cart::whereHas('items')
                ->whereNull('converted_to_order_id')
                ->whereNull('recovered_at')
                ->whereNotNull('abandoned_at')
                ->where('recovery_email_count', $step)
                ->get();

            foreach ($carts as $cart) {
                if ($this->abandonedCartService->needsSequenceStep($cart, $hours)) {
                    $sent = $this->abandonedCartService->sendRecoveryEmail($cart, $sequenceStep);
                    if ($sent) {
                        $this->line("Sent recovery email (step {$sequenceStep}) for cart #{$cart->id}.");
                    }
                }
            }
        }

        $this->info('Abandoned cart processing complete.');

        return Command::SUCCESS;
    }
}
