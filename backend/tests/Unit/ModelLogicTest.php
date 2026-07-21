<?php

namespace Tests\Unit;

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_is_platform_wide_when_vendor_id_is_null(): void
    {
        $coupon = Coupon::create([
            'code' => 'PLATFORM',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => null,
        ]);

        $this->assertTrue($coupon->isPlatformWide());
    }

    public function test_coupon_is_not_platform_wide_when_vendor_id_is_set(): void
    {
        $user = User::factory()->vendorUser()->create();
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store',
            'slug' => 'store-'.uniqid(),
            'status' => VendorStatus::Approved,
        ]);

        $coupon = Coupon::create([
            'code' => 'VENDOR10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendor->id,
        ]);

        $this->assertFalse($coupon->isPlatformWide());
    }

    public function test_coupon_is_expired_when_expires_at_is_in_the_past(): void
    {
        $coupon = Coupon::create([
            'code' => 'EXPIRED',
            'type' => CouponType::Fixed,
            'value' => 5,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($coupon->isExpired());
    }

    public function test_coupon_is_not_expired_when_expires_at_is_in_the_future(): void
    {
        $coupon = Coupon::create([
            'code' => 'VALID',
            'type' => CouponType::Fixed,
            'value' => 5,
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($coupon->isExpired());
    }

    public function test_coupon_is_not_expired_when_expires_at_is_null(): void
    {
        $coupon = Coupon::create([
            'code' => 'FOREVER',
            'type' => CouponType::Fixed,
            'value' => 5,
            'expires_at' => null,
        ]);

        $this->assertFalse($coupon->isExpired());
    }

    protected function makeProduct(float $price): Product
    {
        $user = User::factory()->vendorUser()->create();
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store',
            'slug' => 'store-'.uniqid(),
            'status' => VendorStatus::Approved,
        ]);
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);

        return Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-'.uniqid(),
            'price' => $price,
            'status' => 'active',
        ]);
    }

    public function test_variant_effective_price_uses_override_when_set(): void
    {
        $product = $this->makeProduct(50.00);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price_override' => 35.00,
            'options' => [],
        ]);

        $this->assertEquals('35.00', $variant->effective_price);
    }

    public function test_variant_effective_price_falls_back_to_product_price(): void
    {
        $product = $this->makeProduct(50.00);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price_override' => null,
            'options' => [],
        ]);

        $this->assertEquals('50.00', $variant->effective_price);
    }

    public function test_inventory_available_subtracts_reserved_from_quantity(): void
    {
        $product = $this->makeProduct(10.00);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 3,
        ]);

        $this->assertEquals(7, $inventory->available);
    }

    public function test_inventory_available_never_goes_negative(): void
    {
        $product = $this->makeProduct(10.00);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        // Edge case: reserved somehow exceeds on-hand quantity.
        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 8,
        ]);

        $this->assertEquals(0, $inventory->available);
    }

    public function test_user_role_helpers_return_correct_booleans(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = User::factory()->vendorUser()->create();
        $customer = User::factory()->customer()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isVendor());
        $this->assertFalse($admin->isCustomer());

        $this->assertTrue($vendor->isVendor());
        $this->assertFalse($vendor->isAdmin());

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdmin());
    }
}