<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->paginate(15);

        return $this->success($orders);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success($order->load('items.productVariant.product', 'payment', 'shipments'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);

        $user = $request->user();
        $cart = $user->cart;

        if ($cart === null || $cart->items()->count() === 0) {
            return $this->error('Your cart is empty.', 422);
        }

        $order = DB::transaction(function () use ($cart, $user) {
            $cartItems = $cart->items()->with('productVariant.product')->get();

            $total = 0;
            $orderItemsData = [];

            foreach ($cartItems as $cartItem) {
                $variant = $cartItem->productVariant;

                $inventory = Inventory::where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null || $inventory->available < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for {$variant->product->name}."],
                    ]);
                }

                $unitPrice = $variant->price_override ?? $variant->product->price;

                $orderItemsData[] = [
                    'product_variant_id' => $variant->id,
                    'vendor_id' => $variant->product->vendor_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $unitPrice,
                ];

                $total += $unitPrice * $cartItem->quantity;

                $inventory->decrement('quantity', $cartItem->quantity);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total' => $total,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            $cart->items()->delete();

            return $order;
        });

        return $this->success($order->load('items.productVariant.product'), 'Order placed.', 201);
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        if (in_array($order->status->value, ['shipped', 'completed', 'canceled', 'refunded'])) {
            return $this->error('This order can no longer be canceled.', 422);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                Inventory::where('product_variant_id', $item->product_variant_id)
                    ->increment('quantity', $item->quantity);
            }

            $order->update(['status' => 'canceled']);
        });

        return $this->success($order, 'Order canceled.');
    }
}