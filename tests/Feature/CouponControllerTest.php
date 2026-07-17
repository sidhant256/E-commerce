<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\VendorStatus;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponControllerTest extends TestCase
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

    public function test_vendor_can_create_own_coupon(): void
    {
        $vendor = $this->makeVendor();

        $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/coupons', [
            'code' => 'SAVE10',
            'type' => 'percent',
            'value' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE10',
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_vendor_cannot_assign_coupon_to_another_vendor(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();

        $response = $this->actingAs($vendorA->user, 'sanctum')->postJson('/api/coupons', [
            'code' => 'SNEAKY10',
            'type' => 'percent',
            'value' => 10,
            'vendor_id' => $vendorB->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('coupons', [
            'code' => 'SNEAKY10',
            'vendor_id' => $vendorA->id,
        ]);
    }

    public function test_admin_can_create_platform_wide_coupon(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/coupons', [
            'code' => 'PLATFORM20',
            'type' => 'fixed',
            'value' => 20,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('coupons', [
            'code' => 'PLATFORM20',
            'vendor_id' => null,
        ]);
    }

    public function test_customer_cannot_create_coupon(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/coupons', [
            'code' => 'HACK10',
            'type' => 'percent',
            'value' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_coupon_code_is_rejected(): void
    {
        $vendor = $this->makeVendor();

        Coupon::create([
            'code' => 'DUPE10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/coupons', [
            'code' => 'DUPE10',
            'type' => 'fixed',
            'value' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_vendor_can_update_own_coupon(): void
    {
        $vendor = $this->makeVendor();
        $coupon = Coupon::create([
            'code' => 'UPDATE10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->putJson("/api/coupons/{$coupon->id}", ['value' => 15]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'value' => 15]);
    }

    public function test_vendor_cannot_update_platform_wide_coupon(): void
    {
        $vendor = $this->makeVendor();
        $coupon = Coupon::create([
            'code' => 'PLATFORM5',
            'type' => CouponType::Fixed,
            'value' => 5,
            'vendor_id' => null,
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->putJson("/api/coupons/{$coupon->id}", ['value' => 999]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_own_coupon(): void
    {
        $vendor = $this->makeVendor();
        $coupon = Coupon::create([
            'code' => 'DELETE10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->deleteJson("/api/coupons/{$coupon->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }
}