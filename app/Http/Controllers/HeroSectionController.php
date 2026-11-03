<?php

namespace App\Http\Controllers;

use App\Models\HeroSection;
use Illuminate\Http\Request;

class HeroSectionController extends Controller
{
    private const FIELDS = [
        'eyebrow'        => ['Eyebrow', 100],
        'title'          => ['Title', 150],
        'subtitle'       => ['Subtitle', 255],
        'location'       => ['Location', 100],
        'products_label' => ['Products Label', 50],
        'prices_label'   => ['Prices Label', 50],
        'listings_label' => ['Listings Label', 50],
        'places_label'   => ['Places Label', 50],
        'ticker_label'   => ['Ticker Label', 100],
    ];

    public function index()
    {
        $heroSections = HeroSection::orderBy('id')->get();
        return response()->view('cms.hero_section.index', compact('heroSections'));
    }

    public function edit(string $id)
    {
        $heroSection = HeroSection::findOrFail($id);
        $fields = self::FIELDS;
        return response()->view('cms.hero_section.edit', compact('heroSection', 'fields'));
    }

    public function update(Request $request, string $id)
    {
        $rules = $this->rules();
        $validator = validator($request->all(), $rules);

        if (! $validator->fails()) {
            $hero = HeroSection::findOrFail($id);
            foreach (array_keys($rules) as $column) {
                $hero->{$column} = $request->input($column);
            }
            $isUpdated = $hero->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('hero_section.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    private function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $field => [$label, $max]) {
            $rules["hero_section_{$field}_ar"] = ['required', 'string', "max:{$max}"];
            $rules["hero_section_{$field}_en"] = ['required', 'string', "max:{$max}", 'regex:/[A-Za-z0-9]/'];
        }
        return $rules;
    }
}