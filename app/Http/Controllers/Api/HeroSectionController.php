<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSection;
use App\Support\ApiCache;
use App\Support\HeroData;
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
        $hero = HeroData::get();

        return response()->json([
            'section' => $hero['section'],
            'stats' => $hero['stats'],
            // الشريط له كاشه الخاص (PriceTicker)، وhome.js بيقرا name_ar / name_en / price.
            'ticker' => PriceTicker::publicItems(),
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