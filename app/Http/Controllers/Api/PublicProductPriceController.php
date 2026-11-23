<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Requests\VotePriceRequest;
use App\Models\Area;
use App\Models\PriceVote;
use App\Models\ProductPrice;
use App\Services\PointService;
use App\Support\ApiCache;
use App\Support\PriceTicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use App\Services\ImageProcessor;

class PublicProductPriceController extends Controller
{
private const PER_PAGE = 20;
private const MAX_PER_PAGE = 50;

public function index(Request $request): JsonResponse
{
$validator = validator($request->query(), [
'region' => ['nullable', 'string', 'max:45'],
'category' => ['nullable', 'string', 'max:45'],
'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
]);

if ($validator->fails()) {
return response()->json([
    'icon' => 'error',
    'title' => $validator->getMessageBag()->first(),
], 400);
}

$region = $request->query('region') ?: null;
$category = $request->query('category') ?: null;
$limit = (int) ($request->query('limit') ?: self::PER_PAGE);

$build = fn () => $this->feedPayload($region, $category, $limit);

$pageNumber = Paginator::resolveCurrentPage();

// الاستجابة نفسها لكل الناس (بدون my_vote)، فبتنكاش؛ الصفحات البعيدة والمفاتيح العشوائية ما بتنكاش
$payload = $pageNumber > (int) config('prices.cache.max_page')
? $build()
: ApiCache::remember(
    [ApiCache::PRICES, ApiCache::PRODUCTS],
    'feed:'.md5(json_encode([$region, $category, $limit, $pageNumber])),
    (int) config('prices.cache.feed_ttl'),
    $build,
    fn (array $value) => $value['data'] === [],
);

return response()->json($payload);
}

/** @return array{data: array, next_cursor: null, has_more: bool, total: int} */
private function feedPayload(?string $region, ?string $category, int $limit): array
{
$page = ProductPrice::query()
->approved()
->whereHas('product', function (Builder $q) use ($category) {
    $q->active();

    if ($category !== null) {
        $q->whereHas('category', fn (Builder $c) => $c->where('category_slug', $category));
    }
})
->when($region, fn (Builder $q, string $slug) => $q->whereHas('area', fn (Builder $a) => $a->where('area_slug', $slug)))
->with([
    'product:id,product_name_ar,product_name_en,product_quantity,category_id,unit_id',
    'product.category:id,category_slug',
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
'category' => $price->product?->category?->category_slug,
'price' => $price->product_price_value,
'created_at' => $price->created_at?->toJSON(),
'confirm_count' => $price->confirm_count,
'report_count' => $price->report_count,
])->values();

return [
'data' => $items->all(),
'next_cursor' => null,
'has_more' => $page->hasMorePages(),
'total' => $page->total(),
];
}

public function myVotes(Request $request): JsonResponse
{
$validator = validator($request->query(), [
'price_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_PAGE],
'price_ids.*' => ['integer', 'min:1', 'distinct'],
]);

if ($validator->fails()) {
return response()->json([
    'icon' => 'error',
    'title' => $validator->getMessageBag()->first(),
], 400);
}

// المستخدم من الجلسة فقط (مش من الطلب): ما حدا بيشوف أصوات غيره
$votes = PriceVote::query()
->where('user_id', $request->user()->id)
->whereIn('product_price_id', $validator->validated()['price_ids'])
->orderBy('product_price_id')
->get(['product_price_id', 'price_vote_type'])
->map(fn (PriceVote $vote) => [
    'price_id' => $vote->product_price_id,
    'my_vote' => $vote->price_vote_type,
])
->values();

return response()->json(['data' => $votes]);
}

public function ticker(): JsonResponse
{
return response()->json(['data' => PriceTicker::items()]);
}

public function store(StoreProductPriceRequest $request): JsonResponse
{
$data = $request->validated();
$areaId = Area::where('area_slug', $data['region'])->value('id');

if ($areaId === null) {
return response()->json(['icon' => 'error', 'title' => 'Region not found'], 404);
}

$storedPath = null;
$pointsAwarded = 0;

try {
$price = new ProductPrice;
$price->fill([
    'product_id' => $data['product_id'],
    'area_id' => $areaId,
    'product_price_value' => $data['price'],
    'product_price_store_name' => $data['store_name'],
    'product_price_store_address' => $data['store_address'] ?? null,
    'product_price_store_phone' => $data['store_phone'] ?? null,
]);
$price->user_id = $request->user()->id;
$price->product_price_status = ProductPrice::STATUS_APPROVED;

if ($request->hasFile('receipt_image')) {
    $path = app(ImageProcessor::class)->store($request->file('receipt_image'), 'receipts', 'local', 'receipt');

    if ($path === false) {
        throw new RuntimeException('Failed to store price receipt image');
    }

    $storedPath = $path;
    $price->product_price_receipt_image = $path;
}

// حفظ السعر ومنح النقاط وحدة واحدة: لو فشل المنح بيتراجع السعر كمان
$pointsAwarded = DB::transaction(function () use ($price) {
    $price->save();

    $transaction = app(PointService::class)->award(
        (int) $price->user_id,
        'add_price',
        'product_price',
        (int) $price->id,
    );

    return $transaction?->point_transaction_points ?? 0;
});
} catch (Throwable $e) {
if ($storedPath !== null) {
    Storage::disk('local')->delete($storedPath);
}

report($e);

return response()->json(['icon' => 'error', 'title' => 'Could not save the price'], 500);
}

// السعر معتمد فوراً فبيظهر بالـ feed: بنبطّل كاش الـ feed (بدون ما نلمس كاش الشريط)
ApiCache::pricesChanged();

// ما بنمسح كاش الشريط هون: السعر الجديد بيظهر مع التحديث المجدول الجاي (بدل ما كل إضافة تضرب الداتابيز)

return response()->json([
'data' => [
    'id' => $price->id,
    'status' => $price->product_price_status,
    'points_awarded' => $pointsAwarded,
],
], 201);
}

public function vote(VotePriceRequest $request, string $price): JsonResponse
{
$userId = (int) $request->user()->id;
$vote = $request->validated('vote');

$outcome = DB::transaction(function () use ($price, $userId, $vote) {
$row = ProductPrice::query()->whereKey($price)->lockForUpdate()->first();

if (! $row || $row->product_price_status !== ProductPrice::STATUS_APPROVED) {
    return ['code' => 404, 'title' => 'Price not found'];
}

if ((int) $row->user_id === $userId) {
    return ['code' => 403, 'title' => 'You cannot vote on your own price'];
}

$existing = PriceVote::query()
    ->where('product_price_id', $row->id)
    ->where('user_id', $userId)
    ->first();

if ($vote === null) {
    $existing?->delete();
} elseif ($existing) {
    if ($existing->price_vote_type !== $vote) {
        $existing->price_vote_type = $vote;
        $existing->save();
    }
} else {
    $new = new PriceVote;
    $new->product_price_id = $row->id;
    $new->user_id = $userId;
    $new->price_vote_type = $vote;
    $new->save();
}

$counts = PriceVote::query()
    ->where('product_price_id', $row->id)
    ->selectRaw('price_vote_type, count(*) as total')
    ->groupBy('price_vote_type')
    ->pluck('total', 'price_vote_type');

$row->confirm_count = (int) ($counts[PriceVote::TYPE_CONFIRM] ?? 0);
$row->report_count = (int) ($counts[PriceVote::TYPE_REPORT] ?? 0);

$removed = $row->shouldBeHiddenByReports();

if ($removed) {
    $row->product_price_status = ProductPrice::STATUS_FLAGGED;
}

$row->save();

return [
    'code' => 200,
    'data' => [
        'price_id' => $row->id,
        'my_vote' => $vote,
        'confirm_count' => $row->confirm_count,
        'report_count' => $row->report_count,
        'removed' => $removed,
    ],
];
});

if ($outcome['code'] !== 200) {
return response()->json(['icon' => 'error', 'title' => $outcome['title']], $outcome['code']);
}

// بس لما السعر اختفى بالبلاغات: عدّادات التصويت مش جزء من الشريط
if ($outcome['data']['removed']) {
PriceTicker::refreshAfterChange();
ApiCache::pricesChanged();
}

return response()->json(['data' => $outcome['data']]);
}
}