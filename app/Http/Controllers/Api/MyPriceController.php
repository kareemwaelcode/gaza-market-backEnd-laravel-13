<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "أسعاري": الأسعار اللي أضافها المستخدم الحالي (بكل حالاتها). المستخدم من الجلسة فقط. */
class MyPriceController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $limit = (int) ($request->query('limit') ?: self::PER_PAGE);

        $page = ProductPrice::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'product:id,product_name_ar,product_name_en,product_quantity,unit_id',
                'product.unit:id,unit_slug',
                'area:id,area_slug',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($limit);

        $items = $page->getCollection()->map(fn (ProductPrice $price) => [
            'price_id' => $price->id,
            'product_id' => $price->product_id,
            'product_name_ar' => $price->product?->product_name_ar,
            'product_name_en' => $price->product?->product_name_en,
            'quantity' => $price->product?->product_quantity,
            'unit' => $price->product?->unit?->unit_slug,
            'store_name' => $price->product_price_store_name,
            'region' => $price->area?->area_slug,
            'price' => $price->product_price_value,
            'status' => $price->product_price_status,
            'rejection_reason' => $price->rejection_reason,
            'confirm_count' => $price->confirm_count,
            'report_count' => $price->report_count,
            'created_at' => $price->created_at?->toJSON(),
        ])->values();

        return response()->json([
            'data' => $items->all(),
            'next_cursor' => null,
            'has_more' => $page->hasMorePages(),
            'total' => $page->total(),
        ]);
    }
}