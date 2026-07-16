<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
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

    protected function makeOrderWithVendorItem(User $customer, Vendor $vendor): Order
    {
        $category = Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-'.uniqid(),
            'price' => 20.00,
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'pending',
            'total' => 20.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'vendor_id' => $vendor->id,
            'quantity' => 1,
            'unit_price' => 20.00,
        ]);

        return $order;
    }

    public function test_customer_can_view_own_order(): void
    {
        $customer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertTrue($customer->can('view', $order));
    }

    public function test_other_customer_cannot_view_order(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertFalse($otherCustomer->can('view', $order));
    }

    public function test_vendor_with_item_in_order_can_view_it(): void
    {
        $customer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertTrue($vendor->user->can('view', $order));
    }

    public function test_unrelated_vendor_cannot_view_order(): void
    {
        $customer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $unrelatedVendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertFalse($unrelatedVendor->user->can('view', $order));
    }

    public function test_customer_can_cancel_own_order(): void
    {
        $customer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertTrue($customer->can('cancel', $order));
    }

    public function test_admin_can_view_any_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $vendor = $this->makeVendor();
        $order = $this->makeOrderWithVendorItem($customer, $vendor);

        $this->assertTrue($admin->can('view', $order));
    }
}