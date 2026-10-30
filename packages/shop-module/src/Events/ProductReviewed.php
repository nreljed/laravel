<?php

namespace Nreljed\ShopModule\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nreljed\ShopModule\Models\ProductReview;

class ProductReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProductReview $review,
    ) {
    }
}

