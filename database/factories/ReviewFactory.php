<?php
// database/factories/ReviewFactory.php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => null, // resolved in configure(), unless overridden
            'user_id' => null,    // resolved in configure(), unless overridden
            'order_item_id' => OrderItem::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Review $review) {
            // A review's product_id and user_id must match the actual
            // purchase it's tied to — same "verified purchase" logic the
            // order_item_id unique constraint enforces at the DB level.
            // A review for a product the reviewer never bought would be a
            // silent data-integrity bug, exactly like OrderItem's vendor_id
            // mismatch was.
            if ($review->product_id === null) {
                $review->product_id = $review->orderItem->productVariant->product_id;
            }

            if ($review->user_id === null) {
                $review->user_id = $review->orderItem->order->user_id;
            }
        });
    }
}