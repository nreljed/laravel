<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\ShopModule\Events\ProductReviewed;

class MarketingProductReviewedListener
{
    public function handle(ProductReviewed $event): void
    {
        if (! class_exists(Contact::class) || ! class_exists(WorkflowEngine::class)) {
            return;
        }

        try {
            if (! Schema::hasTable('contacts') || ! Schema::hasTable('workflows')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $review = $event->review;
        $contact = null;

        if ($review->user_id) {
            $user = $review->user;
            if ($user && $user->email) {
                $contact = Contact::where('email', $user->email)->first();
                if (! $contact && $user->email) {
                    $contact = Contact::firstOrCreate(
                        ['email' => $user->email],
                        [
                            'user_id' => $user->id,
                            'first_name' => $user->first_name ?: $user->name,
                            'status' => ContactStatus::Active,
                            'source' => 'shop',
                        ],
                    );
                }
            }
        }

        try {
            app(WorkflowEngine::class)->productReviewed($contact, [
                'review_id' => $review->id,
                'product_id' => $review->product_id,
                'rating' => $review->rating,
                'title' => $review->title,
                'is_approved' => $review->is_approved ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
