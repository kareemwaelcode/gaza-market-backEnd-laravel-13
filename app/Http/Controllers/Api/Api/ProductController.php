<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = new Product($request->safe()->except('product_receipt_image'));
        $product->user_id = $request->user()->id;
        $product->product_status = ProductStatus::Pending;

        if ($request->hasFile('product_receipt_image')) {
            $product->product_receipt_image = $request->file('product_receipt_image')->store('receipts', 'local');
        }

        try {
            $product->save();
        } catch (Throwable $e) {
            if ($product->product_receipt_image) {
                Storage::disk('local')->delete($product->product_receipt_image);
            }

            throw $e;
        }

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $term = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
        ])['q'];

        return ProductResource::collection(
            Product::query()
                ->active()
                ->search($term)
                ->with(['category', 'unit', 'area'])
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
        );
    }
}