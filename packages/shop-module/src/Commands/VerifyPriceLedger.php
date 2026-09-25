<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Services\PriceLedgerService;

/**
 * PR 10C — finds and repairs public price rows that drifted from the product
 * columns they mirror.
 *
 * Before 10C nothing wrote to the ledger after its migration, so an app that
 * has been editing prices in the admin carries stale rows that the storefront
 * keeps serving. The observer repairs a row the next time its product is
 * saved, and this command repairs the rest in one pass.
 */
class VerifyPriceLedger extends Command
{
    protected $signature = 'shop:verify-price-ledger
                            {--fix : Rewrite drifted public rows from the product columns}';

    protected $description = 'Report (and optionally repair) product price rows that no longer match the product price';

    public function handle(PriceLedgerService $ledger): int
    {
        $drifted = $ledger->driftedRows();

        if ($drifted === []) {
            $this->info('Price ledger is in step with the product prices.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d product(s) have a stale ledger price.', count($drifted)));

        foreach ($drifted as $row) {
            $this->line(sprintf(
                '  product #%d: ledger %s, product %s',
                $row['product_id'],
                $this->formatAmount($row['ledger_price']),
                $this->formatAmount($row['product_price']),
            ));
        }

        if (! $this->option('fix')) {
            $this->comment('Re-run with --fix to rewrite the ledger from the product prices.');

            return self::SUCCESS;
        }

        $repaired = 0;

        foreach ($drifted as $row) {
            $product = Product::query()->find($row['product_id']);

            if (! $product) {
                continue;
            }

            $ledger->sync($product);
            $repaired++;
        }

        $this->info("Repaired {$repaired} ledger row(s).");

        if ($ledger->driftedRows() !== []) {
            $this->error('Some rows are still out of step.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
