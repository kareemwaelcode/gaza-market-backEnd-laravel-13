<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\SavedListing;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedListingController extends Controller
{
    private const PER_PAGE = 20;

    // محفوظاتي: المعتمدة فقط، الأحدث حفظاً أولاً. الإعلان اللي ما عاد معتمداً بيختفي من القائمة وصف الحفظ بيضل
    public function index(Request $request): JsonResponse
    {
        $page = Listing::query()
            ->approved()
            ->join('saved_listings', 'saved_listings.listing_id', '=', 'listings.id')
            ->where('saved_listings.user_id', $request->user()->id)
            ->select('listings.*')
            ->with(['area:id,area_name_ar,area_name_en', 'images'])
            ->orderByDesc('saved_listings.saved_listing_created_at')
            ->orderByDesc('saved_listings.id')
            ->paginate(self::PER_PAGE);

        // حقول البطاقة فقط: ما في رقم جوال ولا بيانات صاحب الإعلان
        $items = $page->getCollection()->map(fn (Listing $listing) => [
            'id' => $listing->id,
            'title' => $listing->listing_title,
            'condition' => $listing->listing_condition,
            'price' => $listing->listing_price,
            'is_negotiable' => $listing->listing_is_negotiable,
            'area' => $listing->area ? [
                'name_ar' => $listing->area->area_name_ar,
                'name_en' => $listing->area->area_name_en,
            ] : null,
            'cover' => $listing->images->first()?->url,
            'published_at' => $listing->listing_published_at?->toJSON(),
        ])->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    // حفظ إعلان معتمد. القيد الفريد في الجدول هو الحماية الحقيقية من التكرار حتى مع طلبين متزامنين
    public function store(Request $request, int $listing): JsonResponse
    {
        if (! Listing::query()->approved()->whereKey($listing)->exists()) {
            return $this->notFound();
        }

        try {
            SavedListing::create([
                'user_id' => $request->user()->id,
                'listing_id' => $listing,
            ]);
            $status = 201;
        } catch (UniqueConstraintViolationException) {
            // محفوظ من قبل: نفس النتيجة بدون صف جديد
            $status = 200;
        }

        return response()->json(['data' => ['listing_id' => $listing, 'saved' => true]], $status);
    }

    // إلغاء الحفظ: بيشتغل على أي حالة للإعلان، وإذا مش محفوظ أصلاً ما بيصير خطأ. المستخدم بيحذف صفوفه هو فقط
    public function destroy(Request $request, int $listing): JsonResponse
    {
        SavedListing::query()
            ->where('user_id', $request->user()->id)
            ->where('listing_id', $listing)
            ->delete();

        return response()->json(['data' => ['listing_id' => $listing, 'saved' => false]]);
    }

    // نفس الرد للإعلان غير الموجود والمعلّق والمرفوض والمباع، فما بنكشف حالته
    private function notFound(): JsonResponse
    {
        return response()->json(['icon' => 'error', 'title' => 'Listing not found'], 404);
    }
}