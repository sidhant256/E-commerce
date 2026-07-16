<?php
// database/factories/OrderItemFactory.php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'vendor_id' => null, // resolved in configure(), always overwritten below
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->randomFloat(2, 5, 500),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $orderItem) {
            // vendor_id must always match the variant's real vendor —
            // there's no valid "random" value for this field, so we
            // derive it here rather than trust whatever was passed in,
            // unless the caller explicitly overrode it (rare, but
            // possible for deliberately-testing-a-bad-state scenarios).
            if ($orderItem->vendor_id === null) {
                $orderItem->vendor_id = $orderItem->productVariant->product->vendor_id;
            }
        });
    }
}