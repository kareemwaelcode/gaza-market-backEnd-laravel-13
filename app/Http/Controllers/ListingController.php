<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $filters = validator($request->query(), [
            'status' => ['nullable', Rule::in(Listing::STATUSES)],
            'category' => ['nullable', 'integer'],
            'area' => ['nullable', 'integer'],
        ])->validate();

        $listings = Listing::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'area:id,area_name_ar',
                'category:id,listing_category_name_ar,listing_category_name_en',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('listing_status', $status))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('listing_category_id', $id))
            ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $categories = ListingCategory::ordered()->get(['id', 'listing_category_name_en', 'listing_category_name_ar']);
        $areas = Area::ordered()->get(['id', 'area_name_ar']);

        return response()->view('cms.listing.index', compact('listings', 'categories', 'areas', 'filters'));
    }

    public function show(string $id)
    {
        $listing = Listing::with(['user', 'area.region', 'category', 'images'])->findOrFail($id);

        return response()->view('cms.listing.show', compact('listing'));
    }

    public function approve(string $id)
    {
        $listing = Listing::findOrFail($id);

        $updated = Listing::whereKey($listing->id)
            ->whereIn('listing_status', [Listing::STATUS_PENDING, Listing::STATUS_REJECTED])
            ->update([
                'listing_status' => Listing::STATUS_APPROVED,
                'listing_published_at' => now(),
                'listing_rejection_reason' => null,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['icon' => 'error', 'title' => 'Only pending or rejected listings can be approved'], 409);
        }

        return response()->json(['redirect' => route('listing.show', $listing->id)], 200);
    }

    /**
     * رفض: من pending أو approved فقط، والسبب إجباري ويظهر لصاحب الإعلان.
     * الإعلان المباع لا يُرفض.
     */
    public function reject(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'listing_rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
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
            return response()->json(['icon' => 'error', 'title' => 'Only pending or approved listings can be rejected'], 409);
        }

        return response()->json(['redirect' => route('listing.show', $listing->id)], 200);
    }

    public function destroy(string $id)
    {
        $listing = Listing::with('images')->findOrFail($id);

        // الملفات المحلية بس (روابط https التجريبية ما إلها ملف على القرص)
        $paths = $listing->images
            ->filter(fn ($image) => $image->isLocalFile())
            ->pluck('listing_image_path')
            ->all();

        // صفوف الصور بتنحذف تلقائياً من قاعدة البيانات (CASCADE)
        $listing->delete();

        // الملفات بتنحذف بعد نجاح حذف الإعلان
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }
}