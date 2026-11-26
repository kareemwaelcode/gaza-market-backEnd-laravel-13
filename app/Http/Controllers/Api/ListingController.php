<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\PointService;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * إدارة إعلانات السوق للأدمن (مراجعة: عرض، اعتماد، رفض، حذف).
 * الإضافة والتعديل من المستخدم ما بتصير هون (هي بـ api/market).
 */
class ListingController extends Controller implements HasMiddleware
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
            'status' => ['nullable', Rule::in(Listing::STATUSES)],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $filters = array_filter($validator->validated(), fn ($value) => $value !== null);

        $listings = Listing::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'area:id,area_name_ar',
                'category:id,listing_category_name_ar,listing_category_name_en',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('listing_status', $status))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('listing_category_id', $id))
            ->when($filters['area_id'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->appends($filters);

        return response()->json([
            'status' => true,
            'message' => 'Data of Listings',
            'data' => $listings,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $listing = Listing::with([
            'user:id,user_full_name,user_phone,user_status',
            'area:id,area_name_ar,region_id',
            'area.region:id,region_name_ar',
            'category:id,listing_category_name_ar,listing_category_name_en',
            'images',
        ])->findOrFail($id);

        $listing->images->each(fn ($image) => $image->append('url'));

        return response()->json([
            'status' => true,
            'message' => 'Data of Listing',
            'data' => $listing,
        ], 200);
    }

    public function approve(string $id): JsonResponse
    {
        $listing = Listing::findOrFail($id);

        // تحديث شرطي بعملية وحدة: آمن لو ضغط أدمنين بنفس اللحظة. منح النقاط بنفس الـ transaction: لو فشل بيتراجع الاعتماد
        // (القاعدة add_listing غير موجودة/معطّلة = ما في منح. وإعادة الاعتماد بعد رفض ما بتمنح مرتين: القيد الفريد بجدول النقاط)
        $updated = DB::transaction(function () use ($listing) {
            $rows = Listing::whereKey($listing->id)
                ->whereIn('listing_status', [Listing::STATUS_PENDING, Listing::STATUS_REJECTED])
                ->update([
                    'listing_status' => Listing::STATUS_APPROVED,
                    'listing_published_at' => now(),
                    'listing_rejection_reason' => null,
                    'updated_at' => now(),
                ]);

            if ($rows) {
                app(PointService::class)->award((int) $listing->user_id, 'add_listing', 'listing', (int) $listing->id);
            }

            return $rows;
        });

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Only pending or rejected listings can be approved',
            ], 409);
        }

        // update() بالـ query builder ما بيشغّل observers، فالإبطال يدوي
        ApiCache::marketChanged();

        return response()->json([
            'status' => true,
            'message' => 'Approved Successfully',
            'data' => $listing->fresh(),
        ], 200);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = validator($request->all(), [
            'listing_rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $listing = Listing::findOrFail($id);

        $updated = Listing::whereKey($listing->id)
            ->whereIn('listing_status', [Listing::STATUS_PENDING, Listing::STATUS_APPROVED])
            ->update([
                'listing_status' => Listing::STATUS_REJECTED,
                'listing_published_at' => null,
                'listing_rejection_reason' => trim($request->input('listing_rejection_reason')),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Only pending or approved listings can be rejected',
            ], 409);
        }

        ApiCache::marketChanged();

        return response()->json([
            'status' => true,
            'message' => 'Rejected Successfully',
            'data' => $listing->fresh(),
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $listing = Listing::with('images')->findOrFail($id);

        $paths = $listing->images
            ->filter(fn ($image) => $image->isLocalFile())
            ->pluck('listing_image_path')
            ->all();

        // observer الموديل (CacheInvalidation) بيبطل كاش السوق لو الإعلان معتمد
        $listing->delete();

        if ($paths) {
            Storage::disk('public')->delete($paths);
        }

        Storage::disk('public')->deleteDirectory("listings/{$listing->id}");

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
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