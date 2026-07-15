<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registerCustomer(Request $request)
    {
        
    // Validates the incoming request
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Customer,
        ]);

        $token = $user->createToken($request->userAgent() ?? 'api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function registerVendor(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'store_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

    // Either both User & Vendor succeed, or if anything inside throws an exception, both get rolled back
        $vendor = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::Vendor,
            ]);

    // Generates a URL-friendly slug from the store name
            $baseSlug = Str::slug($validated['store_name']);
            $slug = $baseSlug;
            $suffix = 1;

    // The while loop handles collisions: if that slug is already taken by another vendor
            while (Vendor::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $vendor = Vendor::create([
                'user_id' => $user->id,
                'store_name' => $validated['store_name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'status' => VendorStatus::Pending,
            ]);

    // manually attaches the $user object to the $vendor model's user relationship in memory
            $vendor->setRelation('user', $user);

            return $vendor;
        });
        
    // Issues a Sanctum API token for the newly created user 
        $token = $vendor->user->createToken($request->userAgent() ?? 'api-token')->plainTextToken;

    // Returns the user, the vendor record, and the token as JSON, with HTTP status 201 Created
        return response()->json([
            'user' => $vendor->user,
            'vendor' => $vendor,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

    // Hash::check() hashes the submitted plaintext password using the same algorithm and compares it to the stored hash
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->userAgent() ?? 'api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        // Sanctum's logout here is a single-device logout
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}