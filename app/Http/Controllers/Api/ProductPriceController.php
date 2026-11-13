<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Requests\VotePriceRequest;
use App\Models\Area;
use App\Models\PriceVote;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductPriceController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    // الـ feed العام: الأسعار المعتمدة فقط، الأحدث أولاً (page + limit زي ما الفرونت بيطلب)
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

        $page = ProductPrice::query()
            ->approved()
            // السعر بيظهر طالما المنتج نفسه معتمد (والفئة لو انطلبت)
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

        // أصوات المستخدم الحالي على صفحة الأسعار هاي بس (استعلام واحد، مش N+1)
        $userId = $request->user()?->id;
        $myVotes = $userId === null || $page->isEmpty()
            ? collect()
            : PriceVote::query()
                ->where('user_id', $userId)
                ->whereIn('product_price_id', $page->getCollection()->pluck('id'))
                ->pluck('price_vote_type', 'product_price_id');

        // حقول البطاقة فقط: ما في هاتف المتجر ولا الفاتورة ولا بيانات صاحب السعر
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
            'my_vote' => $myVotes->get($price->id),
        ])->values();

        return response()->json([
            'data' => $items,
            'next_cursor' => null,
            'has_more' => $page->hasMorePages(),
            'total' => $page->total(),
        ]);
    }

    // تسجيل سعر جديد لمنتج موجود: صاحب السعر من المستخدم المسجّل، والحالة pending لحد موافقة الأدمن
    public function store(StoreProductPriceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $areaId = Area::where('area_slug', $data['region'])->value('id');

        if ($areaId === null) {
            return response()->json(['icon' => 'error', 'title' => 'Region not found'], 404);
        }

        $storedPath = null;

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
            $price->product_price_status = ProductPrice::STATUS_PENDING;

            // الفاتورة على القرص الخاص (local) مش public: الأدمن بس بشوفها من لوحة التحكم
            if ($request->hasFile('receipt_image')) {
                $path = $request->file('receipt_image')->store('receipts', 'local');

                if ($path === false) {
                    throw new RuntimeException('Failed to store price receipt image');
                }

                $storedPath = $path;
                $price->product_price_receipt_image = $path;
            }

            $price->save();
        } catch (Throwable $e) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            report($e);

            return response()->json(['icon' => 'error', 'title' => 'Could not save the price'], 500);
        }

        return response()->json([
            'data' => [
                'id' => $price->id,
                'status' => $price->product_price_status,
            ],
        ], 201);
    }

    // تأكيد/إبلاغ/إلغاء صوت: صوت واحد لكل مستخدم على السعر، والعدّادات بتتحدّث بنفس الـ transaction
    public function vote(VotePriceRequest $request, string $price): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $vote = $request->validated('vote');

        $outcome = DB::transaction(function () use ($price, $userId, $vote) {
            // قفل صف السعر: تصويتين بنفس اللحظة على نفس السعر بيتنفذوا ورا بعض
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

            // العدّادات من جدول الأصوات نفسه: ما بنزيد/بننقص يدوياً (بتتصحح لو انحرفت)
            $counts = PriceVote::query()
                ->where('product_price_id', $row->id)
                ->selectRaw('price_vote_type, count(*) as total')
                ->groupBy('price_vote_type')
                ->pluck('total', 'price_vote_type');

            $row->confirm_count = (int) ($counts[PriceVote::TYPE_CONFIRM] ?? 0);
            $row->report_count = (int) ($counts[PriceVote::TYPE_REPORT] ?? 0);

            $removed = $row->report_count > ProductPrice::MAX_REPORTS;

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

        return response()->json(['data' => $outcome['data']]);
    }
}