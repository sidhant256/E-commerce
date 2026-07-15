<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVendor(VendorStatus $status = VendorStatus::Approved): Vendor
    {
        $user = User::factory()->vendorUser()->create();

        return Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store '.$user->id,
            'slug' => 'store-'.$user->id,
            'status' => $status,
        ]);
    }

    public function test_admin_can_approve_vendor(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $this->assertTrue($admin->can('approve', $vendor));
    }

    public function test_vendor_cannot_approve_self(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $this->assertFalse($vendor->user->can('approve', $vendor));
    }

    public function test_vendor_can_update_own_profile(): void
    {
        $vendor = $this->makeVendor();

        $this->assertTrue($vendor->user->can('update', $vendor));
    }

    public function test_vendor_cannot_update_other_vendors_profile(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();

        $this->assertFalse($vendorB->user->can('update', $vendorA));
    }

    public function test_anyone_can_view_approved_vendor(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Approved);
        $customer = User::factory()->customer()->create();

        $this->assertTrue($customer->can('view', $vendor));
    }

    public function test_only_owner_can_view_pending_vendor(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);
        $customer = User::factory()->customer()->create();

        $this->assertTrue($vendor->user->can('view', $vendor));
        $this->assertFalse($customer->can('view', $vendor));
    }
}