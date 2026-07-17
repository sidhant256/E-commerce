<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVendor(): Vendor
    {
        $user = User::factory()->vendorUser()->create();

        return Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store '.$user->id,
            'slug' => 'store-'.$user->id,
            'status' => VendorStatus::Approved,
        ]);
    }

    protected function makeVariantWithStock(int $stock, float $price = 20.00): ProductVariant
    {
        $vendor = $this->makeVendor();
        $category = Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-'.uniqid(),
            'price' => $price,
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => $stock,
            'reserved_quantity' => 0,
        ]);

        return $variant;
    }

    public function test_checkout_creates_order_from_cart_and_clears_cart(): void
    {
        $customer = User::factory()->customer()->create();
        $cart = Cart::create(['user_id' => $customer->id]);
        $variant = $this->makeVariantWithStock(10, 25.00);

        $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders');

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'total' => 50.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 25.00,
        ]);
        $this->assertEquals(0, $cart->items()->count());
    }

    public function test_checkout_decrements_inventory(): void
    {
        $customer = User::factory()->customer()->create();
        $cart = Cart::create(['user_id' => $customer->id]);
        $variant = $this->makeVariantWithStock(10);

        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 3]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders')->assertStatus(201);

        $this->assertDatabaseHas('inventory', [
            'product_variant_id' => $variant->id,
            'quantity' => 7,
        ]);
    }

    public function test_checkout_fails_with_insufficient_stock(): void
    {
        $customer = User::factory()->customer()->create();
        $cart = Cart::create(['user_id' => $customer->id]);
        $variant = $this->makeVariantWithStock(2);

        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders');

        $response->assertStatus(422);
        $this->assertDatabaseHas('inventory', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $customer = User::factory()->customer()->create();
        Cart::create(['user_id' => $customer->id]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders');

        $response->assertStatus(422);
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        $customer = User::factory()->customer()->create();
        $cart = Cart::create(['user_id' => $customer->id]);
        $variant = $this->makeVariantWithStock(10);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

        $orderResponse = $this->actingAs($customer, 'sanctum')->postJson('/api/orders');
        $orderId = $orderResponse->json('data.id');

        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'canceled']);
        $this->assertDatabaseHas('inventory', ['product_variant_id' => $variant->id, 'quantity' => 10]);
    }

    public function test_customer_cannot_cancel_other_customers_order(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $cart = Cart::create(['user_id' => $customer->id]);
        $variant = $this->makeVariantWithStock(10);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $orderResponse = $this->actingAs($customer, 'sanctum')->postJson('/api/orders');
        $orderId = $orderResponse->json('data.id');

        $response = $this->actingAs($otherCustomer, 'sanctum')->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(403);
    }

    public function test_guest_cannot_checkout(): void
    {
        $response = $this->postJson('/api/orders');

        $response->assertStatus(401);
    }
}