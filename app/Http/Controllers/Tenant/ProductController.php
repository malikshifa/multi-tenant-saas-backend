<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProductRequest;
use App\Http\Resources\Tenant\ProductResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\ProductService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service
    ) {}

    public function index()
    {
        return ProductResource::collection(Product::latest()->paginate(20));
    }

    public function store(ProductRequest $request)
    {
        $product = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => ProductResource::make($product),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'data' => ProductResource::make($product),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product = $this->service->update($product, $request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => ProductResource::make($product),
        ]);
    }

    public function destroy(Product $product)
    {
        $this->service->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
