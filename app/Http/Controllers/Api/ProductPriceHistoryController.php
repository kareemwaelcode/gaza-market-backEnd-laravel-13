<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\ApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

/**
 * تاريخ أسعار منتج: عام، بدون جلسة، ومعتمد فقط.
 * المنتجات الفعّالة المتطابقة (نفس الاسم والكمية والوحدة) بتنحسب كمنتج واحد، مثل البحث.
 */
class ProductPriceHistoryController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    // سقف أمان لعدد المنتجات المتطابقة اللي بنجمع أسعارها
    private const MAX_SIBLINGS = 500;

    public function index(Request $request, string $product): JsonResponse
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

        $item = Product::query()
            ->active()
            ->with('unit:id,unit_slug')
            ->find($product);

        if ($item === null) {
            return response()->json(['icon' => 'error', 'title' => 'Product not found'], 404);
        }

        $limit = (int) ($request->query('limit') ?: self::PER_PAGE);
        $pageNumber = Paginator::resolveCurrentPage();
        $build = fn () => $this->payload($item, $limit);

        $payload = $pageNumber > (int) config('prices.cache.max_page')
            ? $build()
            : ApiCache::remember(
                [ApiCache::PRICES, ApiCache::PRODUCTS],
                'history:'.$item->id.':'.md5(json_encode([$limit, $pageNumber])),
                (int) config('prices.cache.feed_ttl'),
                $build,
                fn (array $value) => $value['data'] === [],
            );

        return response()->json($payload);
    }

    /** @return array{product: array, data: array, next_cursor: null, has_more: bool, total: int} */
    private function payload(Product $item, int $limit): array
    {
        $ids = Product::query()
            ->active()
            ->where('unit_id', $item->unit_id)
            ->where('product_quantity', $item->product_quantity)
            ->where(fn (Builder $q) => $this->sameName($q, 'product_name_ar', $item->product_name_ar))
            ->where(fn (Builder $q) => $this->sameName($q, 'product_name_en', $item->product_name_en))
            ->orderBy('id')
            ->limit(self::MAX_SIBLINGS)
            ->pluck('id');

        $page = ProductPrice::query()
            ->approved()
            ->whereIn('product_id', $ids)
            ->with('area:id,area_slug')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($limit);

        $items = $page->getCollection()->map(fn (ProductPrice $price) => [
            'price_id' => $price->id,
            'price' => $price->product_price_value,
            'store_name' => $price->product_price_store_name,
            'region' => $price->area?->area_slug,
            'created_at' => $price->created_at?->toJSON(),
            'confirm_count' => $price->confirm_count,
            'report_count' => $price->report_count,
        ])->values();

        return [
            'product' => [
                'id' => $item->id,
                'name_ar' => $item->product_name_ar,
                'name_en' => $item->product_name_en,
                'quantity' => $item->product_quantity,
                'unit' => $item->unit?->unit_slug,
            ],
            'data' => $items->all(),
            'next_cursor' => null,
            'has_more' => $page->hasMorePages(),
            'total' => $page->total(),
        ];
    }

    private function sameName(Builder $query, string $column, ?string $value): void
    {
        $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }
}