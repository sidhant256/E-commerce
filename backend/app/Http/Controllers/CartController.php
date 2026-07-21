<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $cart = $this->cartForCustomer($request);

        return $this->success(
            $cart->load('items.productVariant.product.vendor'),
        );
    }

    public function storeItem(Request $request)
    {
        $cart = $this->cartForCustomer($request);

        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $item = $cart->items()
            ->where('product_variant_id', $validated['product_variant_id'])
            ->first();

        if ($item) {
            $item->increment('quantity', $validated['quantity']);
        } else {
            $item = $cart->items()->create($validated);
        }

        return $this->success(
            $item->refresh()->load('productVariant.product.vendor'),
            'Cart item saved.',
            201,
        );
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->update($validated);

        return $this->success(
            $cartItem->refresh()->load('productVariant.product.vendor'),
            'Cart item updated.',
        );
    }

    public function destroyItem(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);

        $cartItem->delete();

        return $this->success(null, 'Cart item removed.');
    }

    private function cartForCustomer(Request $request)
    {
        if (! $request->user()->isCustomer()) {
            abort(403, 'Only customers can manage a cart.');
        }

        return $request->user()->cart()->firstOrCreate();
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        if (! $request->user()->isCustomer() || $cartItem->cart->user_id !== $request->user()->id) {
            abort(403, 'This cart item does not belong to you.');
        }
    }
}
