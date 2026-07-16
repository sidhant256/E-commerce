<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrderItemFor(User $customer): OrderItem
    {
        $vendorUser = User::factory()->vendorUser()->create();
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'store_name' => 'Store '.$vendorUser->id,
            'slug' => 'store-'.$vendorUser->id,
            'status' => VendorStatus::Approved,
        ]);

        $category = Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-'.uniqid(),
            'price' => 15.00,
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'completed',
            'total' => 15.00,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'vendor_id' => $vendor->id,
            'quantity' => 1,
            'unit_price' => 15.00,
        ]);
    }

    public function test_customer_can_review_their_own_order_item(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $this->assertTrue($customer->can('createForOrderItem', [Review::class, $orderItem]));
    }

    public function test_customer_cannot_review_someone_elses_order_item(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $this->assertFalse($otherCustomer->can('createForOrderItem', [Review::class, $orderItem]));
    }

    public function test_customer_cannot_review_same_order_item_twice(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 5,
        ]);

        $this->assertFalse($customer->can('createForOrderItem', [Review::class, $orderItem]));
    }

    public function test_review_owner_can_update_it(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 4,
        ]);

        $this->assertTrue($customer->can('update', $review));
    }

    public function test_non_owner_cannot_update_review(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 4,
        ]);

        $this->assertFalse($otherCustomer->can('update', $review));
    }
}