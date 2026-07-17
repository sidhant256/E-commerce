<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use App\Traits\ApiResponse;

class CouponController extends Controller
{
    use ApiResponse;

    public function store(StoreCouponRequest $request)
    {
        $this->authorize('create', Coupon::class);

        $validated = $request->validated();
        $user = $request->user();

        if ($user->isAdmin()) {
            $vendorId = $validated['vendor_id'] ?? null;
        } else {
            $vendorId = $user->vendor->id;
        }

        $coupon = Coupon::create([
            ...$validated,
            'vendor_id' => $vendorId,
        ]);

        return $this->success($coupon, 'Coupon created.', 201);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        $coupon->update($request->validated());

        return $this->success($coupon, 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return $this->success(null, 'Coupon deleted.');
    }
}