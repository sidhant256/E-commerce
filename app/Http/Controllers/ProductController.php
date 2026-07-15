<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $products = Product::where('status', 'active')
            ->with(['vendor', 'category'])
            ->paginate(15);

        return $this->success($products);
    }

    public function mine(Request $request)
    {
        $vendor = $request->user()->vendor;

        if ($vendor === null) {
            return $this->error('You do not have a vendor account.', 403);
        }

        $products = Product::where('vendor_id', $vendor->id)
            ->with('category')
            ->paginate(15);

        return $this->success($products);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,active,inactive'],
        ]);

        $product = Product::create([
            ...$validated,
            'vendor_id' => $request->user()->vendor->id,
            'slug' => \Illuminate\Support\Str::slug($validated['name']).'-'.uniqid(),
            'status' => $validated['status'] ?? 'draft',
        ]);

        return $this->success($product, 'Product created.', 201);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return $this->success($product->load(['vendor', 'category', 'variants']));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:draft,active,inactive'],
        ]);

        $product->update($validated);

        return $this->success($product, 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return $this->success(null, 'Product deleted.');
    }
}