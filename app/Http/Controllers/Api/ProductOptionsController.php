<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Category;
use App\Models\Unit;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/product-options  (عام - قراءة فقط)
 *
 * يرجّع كل ما يحتاجه فورم "منتج جديد" في طلب واحد:
 * الفئات الفعّالة + الوحدات الفعّالة + المناطق، مع الـ ids الحقيقية
 * اللي يتحقق منها StoreProductRequest (category_id / unit_id / area_id).
 *
 * - بدون pagination (الفورم يحتاج القائمة كاملة).
 * - الكاش يُفرَّغ تلقائياً عند أي تعديل على Category/Unit/Area (CacheInvalidation).
 */
class ProductOptionsController extends Controller
{
public function __invoke(): JsonResponse
{
$data = ApiCache::lookup('product-options', fn () => [
'categories' => Category::query()
    ->where('category_is_active', true)
    ->ordered()
    ->get(['id', 'category_slug', 'category_name_en', 'category_name_ar'])
    ->map(fn (Category $c) => [
        'id' => $c->id,
        'slug' => $c->category_slug,
        'name_en' => $c->category_name_en,
        'name_ar' => $c->category_name_ar,
    ])->all(),

'units' => Unit::query()
    ->where('unit_is_active', true)
    ->ordered()
    ->get(['id', 'unit_slug', 'unit_name_en', 'unit_name_ar'])
    ->map(fn (Unit $u) => [
        'id' => $u->id,
        'slug' => $u->unit_slug,
        'name_en' => $u->unit_name_en,
        'name_ar' => $u->unit_name_ar,
    ])->all(),

'areas' => Area::query()
    ->ordered()
    ->get(['id', 'area_slug', 'area_name_en', 'area_name_ar'])
    ->map(fn (Area $a) => [
        'id' => $a->id,
        'slug' => $a->area_slug,
        'name_en' => $a->area_name_en,
        'name_ar' => $a->area_name_ar,
    ])->all(),
], paged: false);

return response()->json([
'status' => true,
'message' => 'Product form options',
'data' => $data,
], 200);
}
}