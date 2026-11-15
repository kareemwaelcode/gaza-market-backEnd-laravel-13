<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MarketListingController extends Controller
{
    private const LOCK_SECONDS = 15;

    private const DUPLICATE_WINDOW_SECONDS = 60;

    private const OWNER_PER_PAGE = 50;

    private const VISIBLE_TO_OWNER = [Listing::STATUS_PENDING, Listing::STATUS_APPROVED];

    public function store(StoreListingRequest $request): JsonResponse
    {
        $user = $request->user();

        $lock = Cache::lock("listing-store:{$user->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Your previous request is still being processed',
            ], 409);
        }

        try {
            $existing = $this->findRecentDuplicate($request);

            if ($existing !== null) {
                return $this->listingResponse($existing, 200);
            }

            return $this->createListing($request);
        } finally {
            $lock->release();
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $page = $this->ownerQuery($request->user()->id)
            ->latest('id')
            ->simplePaginate(self::OWNER_PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Listing $listing) => $this->ownerPayload($listing))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    public function showMine(Request $request, int $listing): JsonResponse
    {
        $found = $this->ownerQuery($request->user()->id)->find($listing);

        if ($found === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->ownerPayload($found)]);
    }

    public function update(UpdateListingRequest $request, int $listing): JsonResponse
    {
        $user = $request->user();
        $storedPaths = [];
        $oldPaths = [];
        $outcome = null;

        try {
            $result = DB::transaction(function () use ($request, $user, $listing, &$storedPaths, &$oldPaths, &$outcome) {
                $model = Listing::query()
                    ->where('user_id', $user->id)
                    ->whereKey($listing)
                    ->lockForUpdate()
                    ->first();

                if ($model === null) {
                    $outcome = 'missing';

                    return null;
                }

                if ($model->listing_status !== Listing::STATUS_PENDING) {
                    $outcome = 'locked';

                    return null;
                }

                $model->fill($request->safe()->except(['images', 'listing_is_negotiable']));
                $model->listing_is_negotiable = $request->boolean('listing_is_negotiable');
                $model->save();

                if ($request->hasFile('images')) {
                    $oldPaths = $model->images()->get()
                        ->filter(fn ($image) => $image->isLocalFile())
                        ->pluck('listing_image_path')
                        ->all();

                    ListingImage::where('listing_id', $model->id)->delete();

                    foreach (array_values($request->file('images')) as $index => $file) {
                        $path = $file->store("listings/{$model->id}", 'public');

                        if ($path === false) {
                            throw new RuntimeException('Failed to store listing image');
                        }

                        $storedPaths[] = $path;

                        $model->images()->create([
                            'listing_image_path' => $path,
                            'listing_image_sort_order' => $index + 1,
                        ]);
                    }
                }

                return $model->load('images');
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($storedPaths);
            report($e);

            return response()->json(['icon' => 'error', 'title' => 'Could not update the listing'], 500);
        }

        if ($outcome === 'missing') {
            return $this->notFound();
        }

        if ($outcome === 'locked') {
            return $this->conflict('Only listings under review can be edited');
        }

        if ($oldPaths) {
            Storage::disk('public')->delete($oldPaths);
        }

        return $this->listingResponse($result, 200);
    }

    public function destroy(Request $request, int $listing): JsonResponse
    {
        $user = $request->user();
        $outcome = null;
        $paths = [];

        try {
            DB::transaction(function () use ($user, $listing, &$outcome, &$paths) {
                $model = Listing::query()
                    ->where('user_id', $user->id)
                    ->whereKey($listing)
                    ->lockForUpdate()
                    ->first();

                if ($model === null) {
                    $outcome = 'missing';

                    return;
                }

                if ($model->listing_status !== Listing::STATUS_PENDING) {
                    $outcome = 'locked';

                    return;
                }

                $paths = $model->images()->get()
                    ->filter(fn ($image) => $image->isLocalFile())
                    ->pluck('listing_image_path')
                    ->all();

                $model->delete();
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json(['icon' => 'error', 'title' => 'Could not delete the listing'], 500);
        }

        if ($outcome === 'missing') {
            return $this->notFound();
        }

        if ($outcome === 'locked') {
            return $this->conflict('Only listings under review can be deleted');
        }

        if ($paths) {
            Storage::disk('public')->delete($paths);
        }

        Storage::disk('public')->deleteDirectory("listings/{$listing}");

        return response()->json(['data' => ['id' => $listing, 'deleted' => true]]);
    }

    public function sold(Request $request, int $listing): JsonResponse
    {
        $userId = $request->user()->id;

        $owned = Listing::query()->where('user_id', $userId)->whereKey($listing)->exists();

        if (! $owned) {
            return $this->notFound();
        }

        $updated = Listing::query()
            ->where('user_id', $userId)
            ->whereKey($listing)
            ->where('listing_status', Listing::STATUS_APPROVED)
            ->update([
                'listing_status' => Listing::STATUS_SOLD,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return $this->conflict('Only approved listings can be marked as sold');
        }

        ApiCache::marketChanged();

        return response()->json(['data' => ['id' => $listing, 'status' => Listing::STATUS_SOLD]]);
    }

    private function ownerQuery(int $userId)
    {
        return Listing::query()
            ->where('user_id', $userId)
            ->whereIn('listing_status', self::VISIBLE_TO_OWNER)
            ->with(['area:id,area_slug,area_name_en,area_name_ar', 'category:id,listing_category_slug,listing_category_name_en,listing_category_name_ar', 'images']);
    }

    private function ownerPayload(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'status' => $listing->listing_status,
            'title' => $listing->listing_title,
            'condition' => $listing->listing_condition,
            'description' => $listing->listing_description,
            'price' => $listing->listing_price,
            'is_negotiable' => $listing->listing_is_negotiable,
            'phone' => $listing->listing_phone,
            'whatsapp' => $listing->listing_whatsapp,
            'area_id' => $listing->area_id,
            'listing_category_id' => $listing->listing_category_id,
            'region' => $listing->area?->area_slug,
            'area' => $listing->area ? [
                'name_ar' => $listing->area->area_name_ar,
                'name_en' => $listing->area->area_name_en,
            ] : null,
            'category' => $listing->category ? [
                'slug' => $listing->category->listing_category_slug,
                'name_ar' => $listing->category->listing_category_name_ar,
                'name_en' => $listing->category->listing_category_name_en,
            ] : null,
            'images' => $listing->images->pluck('url')->values()->all(),
            'published_at' => $listing->listing_published_at?->toJSON(),
            'created_at' => $listing->created_at?->toJSON(),
        ];
    }

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

    private function notFound(): JsonResponse
    {
        return response()->json(['icon' => 'error', 'title' => 'Listing not found'], 404);
    }

    private function conflict(string $title): JsonResponse
    {
        return response()->json(['icon' => 'error', 'title' => $title], 409);
    }
}