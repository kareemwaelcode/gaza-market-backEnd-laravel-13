<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceVote;
use App\Models\ProductPrice;
use App\Support\ApiCache;
use App\Support\PriceTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * مراجعة الأسعار المبلّغ عنها للأدمن (اعتماد، رفض، حذف).
 * إضافة السعر والتصويت بيضلوا بـ Api\ProductPriceController.
 */
class ProductPriceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'status' => ['nullable', Rule::in(ProductPrice::STATUSES)],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $filters = array_filter($validator->validated(), fn ($value) => $value !== null);

        $prices = ProductPrice::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'product:id,product_name_ar,product_name_en,product_quantity,category_id,unit_id',
                'product.category:id,category_name_ar,category_name_en',
                'product.unit:id,unit_name_ar,unit_name_en',
                'area:id,area_name_ar',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('product_price_status', $status))
            ->when($filters['area_id'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->whereHas('product', fn ($p) => $p->where('category_id', $id)))
            ->orderByRaw("case product_price_status when 'pending' then 0 when 'flagged' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->appends($filters);

        return response()->json([
            'status' => true,
            'message' => 'Data of Product Prices',
            'data' => $prices,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $price = ProductPrice::with([
            'user:id,user_full_name,user_phone,user_status',
            'product.category',
            'product.unit',
            'area:id,area_name_ar,region_id',
            'area.region:id,region_name_ar',
            'votes' => fn ($q) => $q->orderBy('id'),
            'votes.user:id,user_full_name,user_phone',
        ])->findOrFail($id);

        $price->makeVisible('product_price_store_phone');
        $price->setAttribute('has_receipt', filled($price->product_price_receipt_image));

        return response()->json([
            'status' => true,
            'message' => 'Data of Product Price',
            'data' => $price,
        ], 200);
    }

    public function receipt(string $id)
    {
        $price = ProductPrice::findOrFail($id);
        $path = $price->product_price_receipt_image;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function approve(string $id): JsonResponse
    {
        $price = ProductPrice::findOrFail($id);

        $approved = DB::transaction(function () use ($price) {
            $row = ProductPrice::query()->whereKey($price->id)->lockForUpdate()->first();

            if (! $row || $row->product_price_status === ProductPrice::STATUS_APPROVED) {
                return false;
            }

            // سعر مبلّغ عنه: بنصفّر التبليغات (التأكيدات بتضل)
            if ($row->product_price_status === ProductPrice::STATUS_FLAGGED || $row->report_count > ProductPrice::MAX_REPORTS) {
                PriceVote::query()
                    ->where('product_price_id', $row->id)
                    ->where('price_vote_type', PriceVote::TYPE_REPORT)
                    ->delete();

                $row->report_count = 0;
            }

            $row->product_price_status = ProductPrice::STATUS_APPROVED;
            $row->reviewed_at = now();
            $row->save();

            return true;
        });

        if (! $approved) {
            return $this->conflict('This price is already visible');
        }

        PriceTicker::refreshAfterChange();
        ApiCache::pricesChanged();

        return response()->json([
            'status' => true,
            'message' => 'Approved Successfully',
            'data' => $price->fresh(),
        ], 200);
    }

    public function reject(string $id): JsonResponse
    {
        $price = ProductPrice::findOrFail($id);

        $updated = ProductPrice::query()
            ->whereKey($price->id)
            ->whereIn('product_price_status', [
                ProductPrice::STATUS_PENDING,
                ProductPrice::STATUS_APPROVED,
                ProductPrice::STATUS_FLAGGED,
            ])
            ->update([
                'product_price_status' => ProductPrice::STATUS_REJECTED,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return $this->conflict('This price is already hidden');
        }

        PriceTicker::refreshAfterChange();
        ApiCache::pricesChanged();

        return response()->json([
            'status' => true,
            'message' => 'Rejected Successfully',
            'data' => $price->fresh(),
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $price = ProductPrice::findOrFail($id);

        $deleted = ProductPrice::whereKey($price->id)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'Price not found'], 404);
        }

        if ($price->product_price_receipt_image) {
            Storage::disk('local')->delete($price->product_price_receipt_image);
        }

        PriceTicker::refreshAfterChange();
        ApiCache::pricesChanged();

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message], 409);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}