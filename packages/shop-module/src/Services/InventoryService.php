<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Models\StockAdjustment;

class InventoryService
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    public function validateStock(Product $product, int $quantity, ?ProductVariation $variation = null): bool
    {
        if ($variation) {
            if ($variation->usesParentStock()) {
                if ($variation->backorders !== 'no') {
                    return true;
                }

                return ($product->stock_quantity + $product->incoming_stock) >= $quantity;
            }
            if ($variation->managesOwnStock()) {
                if ($variation->backorders !== 'no') {
                    return true;
                }

                return ($variation->stock_quantity + $product->incoming_stock) >= $quantity;
            }

            return true;
        }

        if ($product->track_stock) {
            if ($product->backorders !== 'no') {
                return true;
            }

            return ($product->stock_quantity + $product->incoming_stock) >= $quantity;
        }

        return true;
    }

    public function adjust(
        Product $product,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?ProductVariation $variation = null,
    ): StockAdjustment {
        if ($variation) {
            $stockBefore = (int) $variation->stock_quantity;
            $newQuantity = $stockBefore + $quantity;
            $stockAfter = max(0, $newQuantity);

            $variation->update(['stock_quantity' => $stockAfter]);

            if (! $product->relationLoaded('variations')) {
                $product = $product->fresh();
            }

            $this->dispatchStockChanged($product, $type, $stockBefore, $stockAfter, $variation);
        } else {
            $stockBefore = (int) $product->stock_quantity;
            $newQuantity = $stockBefore + $quantity;
            $stockAfter = max(0, $newQuantity);

            $product->update(['stock_quantity' => $stockAfter]);

            $this->dispatchStockChanged($product, $type, $stockBefore, $stockAfter);
        }

        return StockAdjustment::create([
            'product_id' => $product->id,
            'variation_id' => $variation?->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
        ]);
    }

    public function recordSale(Product $product, int $quantity, int $orderId, ?int $userId = null, ?ProductVariation $variation = null): ?StockAdjustment
    {
        if ($variation) {
            if ($variation->usesParentStock()) {
                // Inherit from parent product
                if (! $product->track_stock) {
                    return null;
                }

                return $this->adjust($product, 'sale', -$quantity, "Order #{$orderId} (variation)", 'order', $orderId, $userId);
            }
            if (! $variation->managesOwnStock()) {
                return null;
            }
        } elseif (! $product->track_stock) {
            return null;
        }

        return $this->adjust(
            $product,
            'sale',
            -$quantity,
            "Order #{$orderId}",
            'order',
            $orderId,
            $userId,
            $variation,
        );
    }

    public function recordRefund(Product $product, int $quantity, int $orderId, ?int $userId = null, ?ProductVariation $variation = null): ?StockAdjustment
    {
        if ($variation) {
            if ($variation->usesParentStock()) {
                if (! $product->track_stock) {
                    return null;
                }

                return $this->adjust($product, 'refund', $quantity, "Refund order #{$orderId} (variation)", 'order', $orderId, $userId);
            }
            if (! $variation->managesOwnStock()) {
                return null;
            }
        } elseif (! $product->track_stock) {
            return null;
        }

        return $this->adjust(
            $product,
            'refund',
            $quantity,
            "Refund order #{$orderId}",
            'order',
            $orderId,
            $userId,
            $variation,
        );
    }

    public function recordSaleForOrder(Order $order, ?int $userId = null): void
    {
        $order->loadMissing('items.product', 'items.variation');

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            $variation = null;
            if ($item->product_variation_id) {
                $variation = $item->variation ?? ProductVariation::find($item->product_variation_id);
            }

            $this->recordSale($product, $item->quantity, $order->id, $userId, $variation);
        }
    }

    public function recordRefundForOrder(Order $order, ?int $userId = null): void
    {
        $order->loadMissing('items.product', 'items.variation');

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            $variation = null;
            if ($item->product_variation_id) {
                $variation = $item->variation ?? ProductVariation::find($item->product_variation_id);
            }

            $this->recordRefund($product, $item->quantity, $order->id, $userId, $variation);
        }
    }

    public function manualAdjust(
        Product $product,
        int $quantity,
        string $reason,
        ?int $userId = null,
        ?ProductVariation $variation = null,
    ): StockAdjustment {
        return $this->adjust(
            $product,
            'manual',
            $quantity,
            $reason,
            'adjustment',
            null,
            $userId,
            $variation,
        );
    }

    public function getHistory(Product $product, int $perPage = 20)
    {
        return StockAdjustment::where('product_id', $product->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    protected function dispatchStockChanged(
        Product $product,
        string $type,
        int $stockBefore,
        int $stockAfter,
        ?ProductVariation $variation = null,
    ): void {
        $product->refresh();

        $variationSku = $variation?->sku;
        $variationLabel = $variation?->attribute_label;

        $this->webhookService->dispatch('product.stock_changed', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'variation_id' => $variation?->id,
            'variation_sku' => $variationSku,
            'variation_label' => $variationLabel,
            'type' => $type,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'stock_status' => $variation ? $variation->stock_status : $product->stock_status,
        ]);

        if ($product->isLowStock()) {
            $product->loadMissing('categories');
            $this->webhookService->dispatch('product.low_stock', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
            ]);
        }
    }
}
