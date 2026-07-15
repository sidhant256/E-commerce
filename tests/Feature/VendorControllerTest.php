<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorControllerTest extends TestCase
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

    public function test_index_returns_only_approved_vendors(): void
    {
        $this->makeVendor(VendorStatus::Approved);
        $this->makeVendor(VendorStatus::Pending);

        $response = $this->getJson('/api/vendors');

        $response->assertStatus(200);
        $statuses = collect($response->json('data.data'))->pluck('status');
        $this->assertTrue($statuses->every(fn ($s) => $s === 'approved'));
    }

    public function test_vendor_can_update_own_profile(): void
    {
        $vendor = $this->makeVendor();

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->putJson("/api/vendors/{$vendor->id}", ['store_name' => 'New Name']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'store_name' => 'New Name']);
    }

    public function test_vendor_cannot_self_approve_via_update(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->putJson("/api/vendors/{$vendor->id}", ['status' => 'approved']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'status' => 'pending']);
    }

    public function test_vendor_cannot_update_other_vendors_profile(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();

        $response = $this->actingAs($vendorB->user, 'sanctum')
            ->putJson("/api/vendors/{$vendorA->id}", ['store_name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_admin_can_approve_pending_vendor(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/vendors/{$vendor->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'status' => 'approved']);
    }

    public function test_vendor_cannot_approve_self(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->postJson("/api/vendors/{$vendor->id}/approve");

        $response->assertStatus(403);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'status' => 'pending']);
    }

    public function test_admin_can_suspend_vendor(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = $this->makeVendor(VendorStatus::Approved);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/vendors/{$vendor->id}/suspend");

        $response->assertStatus(200);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'status' => 'suspended']);
    }

    public function test_owner_can_view_own_pending_vendor(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->getJson("/api/vendors/{$vendor->id}");

        $response->assertStatus(200);
    }

    public function test_stranger_cannot_view_pending_vendor(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);
        $stranger = User::factory()->customer()->create();

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/vendors/{$vendor->id}");

        $response->assertStatus(403);
    }
}