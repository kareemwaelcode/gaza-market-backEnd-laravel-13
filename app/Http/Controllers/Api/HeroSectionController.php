<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSection;
use App\Models\Listing;
use App\Models\Place;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\ApiCache;
use App\Support\PriceTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class HeroSectionController extends Controller implements HasMiddleware
{
    private const FIELDS = [
        'eyebrow' => 100,
        'title' => 150,
        'subtitle' => 255,
        'location' => 100,
        'products_label' => 50,
        'prices_label' => 50,
        'listings_label' => 50,
        'places_label' => 50,
        'ticker_label' => 100,
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api', except: ['index', 'show', 'hero']),
        ];
    }

    public function index(): JsonResponse
    {
        $heroSections = ApiCache::lookup('hero-sections', fn () => HeroSection::orderBy('id')->get()->toArray(), paged: false);

        return response()->json([
            'status' => true,
            'message' => 'Data of Hero Sections',
            'data' => $heroSections,
        ], 200);
    }

    /**
     * GET /api/hero: اللي بتقراه الصفحة الرئيسية (النصوص + الأرقام + الشريط).
     */
    public function hero(): JsonResponse
    {
        // النصوص والأرقام بتتخزن مع بعض في الكاش، وبتتجدد لما أي مجموعة منهم تتغير.
        $hero = ApiCache::remember(
            [ApiCache::LOOKUPS, ApiCache::PRICES, ApiCache::PRODUCTS, ApiCache::MARKET, ApiCache::PLACES],
            'hero',
            (int) config('prices.cache.feed_ttl'),
            function () {
                $section = HeroSection::orderBy('id')->first();

                return [
                    // الحقول المسموحة بس (من $fillable)، من غير id ولا timestamps.
                    'section' => $section?->only($section->getFillable()),
                    'stats' => [
                        'products' => Product::active()->count(),
                        'prices' => ProductPrice::approved()
                            ->whereIn('product_id', Product::active()->select('id'))
                            ->count(),
                        'listings' => Listing::approved()->count(),
                        'places' => Place::approved()->count(),
                    ],
                ];
            },
            fn (array $value) => $value['section'] === null,
        );

        return response()->json([
            'section' => $hero['section'],
            'stats' => $hero['stats'],
            // الشريط له كاشه الخاص (PriceTicker)، وhome.js بيقرا name_ar / name_en / price.
            'ticker' => array_map(fn (array $item) => [
                'name_ar' => $item['product_name_ar'],
                'name_en' => $item['product_name_en'],
                'price' => $item['price'],
            ], PriceTicker::items()),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $heroSection = HeroSection::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Hero Section',
            'data' => $heroSection,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $heroSection = HeroSection::findOrFail($id);

        $rules = $this->rules();
        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $data = [];

        foreach (array_keys($rules) as $column) {
            $data[$column] = $request->input($column);
        }

        $heroSection->update($data);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    private function rules(): array
    {
        $rules = [];

        foreach (self::FIELDS as $field => $max) {
            $rules["hero_section_{$field}_ar"] = ['required', 'string', "max:{$max}"];
            $rules["hero_section_{$field}_en"] = ['required', 'string', "max:{$max}", 'regex:/[A-Za-z0-9]/'];
        }

        return $rules;
    }
}