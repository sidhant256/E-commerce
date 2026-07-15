<?php

namespace App\Http\Controllers;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $vendors = Vendor::where('status', VendorStatus::Approved->value)
            ->paginate(15);

        return $this->success($vendors);
    }

    public function show(Vendor $vendor)
    {
        $this->authorize('view', $vendor);

        return $this->success($vendor->load('user'));
    }

    public function update(Vendor $vendor, Request $request)
    {
        $this->authorize('update', $vendor);

        $validated = $request->validate([
            'store_name' => ['sometimes', 'string', 'max:255'],
            'description'=> ['nullable', 'string'],
        ]);

        $vendor->update($validated);

        return $this->success($vendor, 'Vendor profile updated.');
    }

    public function approve (Vendor $vendor)
    {
        $this->authorize('approve', $vendor);

        $vendor->update(['status' => VendorStatus::Approved]);

        return $this->success($vendor, 'Vendor approved.');
    }

    public function suspend(Vendor $vendor)
    {
        $this->authorize('suspend', $vendor);

        $vendor->update(['status' => VendorStatus::Suspended]);
        
        return $this->success($vendor, 'Vendor suspended.');
    }


}