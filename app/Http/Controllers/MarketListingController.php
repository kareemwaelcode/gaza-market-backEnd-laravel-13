<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MarketListingController extends Controller
{
    public function store(StoreListingRequest $request): JsonResponse
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

        return response()->json([
            'data' => [
                'id' => $listing->id,
                'status' => $listing->listing_status,
                'images' => $listing->images->pluck('url')->all(),
            ],
        ], 201);
    }
}