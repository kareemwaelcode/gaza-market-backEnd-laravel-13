<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MarketListingController extends Controller
{
// مدة القفل بالثواني: أطول من أبطأ طلب رفع متوقع، وبيتحرر دايماً بـ finally
private const LOCK_SECONDS = 15;

// نافذة اعتبار الإرسال المتطابق "نفس الإرسال" (ضغطة مكررة أو إعادة محاولة)
private const DUPLICATE_WINDOW_SECONDS = 60;

public function store(StoreListingRequest $request): JsonResponse
{
$user = $request->user();

// قفل لكل مستخدم: طلبين بنفس اللحظة ما بيشتغلوا بالتوازي
$lock = Cache::lock("listing-store:{$user->id}", self::LOCK_SECONDS);

if (! $lock->get()) {
    return response()->json([
        'icon' => 'error',
        'title' => 'Your previous request is still being processed',
    ], 409);
}

try {
    // ضغطة ثانية بعد ما خلص الطلب الأول: نفس الإعلان بدون إنشاء جديد ولا تخزين صور
    $existing = $this->findRecentDuplicate($request);

    if ($existing !== null) {
        return $this->listingResponse($existing, 200);
    }

    return $this->createListing($request);
} finally {
    $lock->release();
}
}

/**
 * إعلان لنفس المستخدم ونفس البيانات تماماً انعمل خلال آخر 60 ثانية.
 */
private function findRecentDuplicate(StoreListingRequest $request): ?Listing
{
$whatsapp = $request->input('listing_whatsapp');

return Listing::query()
    ->where('user_id', $request->user()->id)
    ->where('listing_title', $request->input('listing_title'))
    ->where('listing_condition', $request->input('listing_condition'))
    ->where('listing_description', $request->input('listing_description'))
    ->where('listing_price', number_format((float) $request->input('listing_price'), 2, '.', ''))
    ->where('listing_is_negotiable', $request->boolean('listing_is_negotiable'))
    ->where('listing_phone', $request->input('listing_phone'))
    ->when(
        $whatsapp === null || $whatsapp === '',
        fn ($q) => $q->whereNull('listing_whatsapp'),
        fn ($q) => $q->where('listing_whatsapp', $whatsapp)
    )
    ->where('listing_category_id', $request->input('listing_category_id'))
    ->where('area_id', $request->input('area_id'))
    ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
    ->orderByDesc('id')
    ->with('images')
    ->first();
}

private function createListing(StoreListingRequest $request): JsonResponse
{
$storedPaths = [];
$directory = null;

try {
    $listing = DB::transaction(function () use ($request, &$storedPaths, &$directory) {
        $listing = new Listing;
        $listing->fill($request->safe()->except(['images', 'listing_is_negotiable']));
        $listing->listing_is_negotiable = $request->boolean('listing_is_negotiable');
        $listing->listing_status = Listing::STATUS_PENDING;
        $listing->user_id = $request->user()->id;
        $listing->save();

        $directory = "listings/{$listing->id}";

        foreach (array_values($request->file('images')) as $index => $file) {
            $path = $file->store($directory, 'public');

            if ($path === false) {
                throw new RuntimeException('Failed to store listing image');
            }

            $storedPaths[] = $path;

            $listing->images()->create([
                'listing_image_path' => $path,
                'listing_image_sort_order' => $index + 1,
            ]);
        }

        return $listing->load('images');
    });
} catch (Throwable $e) {
    Storage::disk('public')->delete($storedPaths);

    if ($directory !== null) {
        Storage::disk('public')->deleteDirectory($directory);
    }

    report($e);

    return response()->json(['icon' => 'error', 'title' => 'Could not save the listing'], 500);
}

return $this->listingResponse($listing, 201);
}

private function listingResponse(Listing $listing, int $status): JsonResponse
{
return response()->json([
    'data' => [
        'id' => $listing->id,
        'status' => $listing->listing_status,
        'images' => $listing->images->pluck('url')->all(),
    ],
], $status);
}
}