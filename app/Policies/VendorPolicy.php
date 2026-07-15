<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\Response;

class VendorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vendor $vendor): bool
    {
        return $vendor->status->value === 'approved' || $user->id === $vendor->user_id;
    }
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vendor $vendor): bool
    {
        return $user->id === $vendor->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function approve(User $user, Vendor $vendor): bool
    {
        return false; // only admins, handled by before()
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function suspend(User $user, Vendor $vendor): bool
    {
        return false; // only admins, handled by before()
    }
}