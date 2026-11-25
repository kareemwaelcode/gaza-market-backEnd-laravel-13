<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Regions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarAreaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_navbar_renders_arabic_and_english_names_for_regions_and_areas(): void
    {
        $region = Regions::create([
            'region_slug' => 'central-gaza',
            'region_name_en' => 'Central Gaza',
            'region_name_ar' => 'وسط غزة',
            'region_sort_order' => 1,
        ]);

        Area::create([
            'area_slug' => 'nuseirat',
            'area_name_en' => 'Nuseirat',
            'area_name_ar' => 'النصيرات',
            'area_sort_order' => 1,
            'region_id' => $region->id,
        ]);

        $this->get('/gazamarket/index')
            ->assertOk()
            ->assertSee('data-name-ar="وسط غزة"', false)
            ->assertSee('data-name-en="Central Gaza"', false)
            ->assertSee('data-area="Nuseirat"', false)
            ->assertSee('data-name-ar="النصيرات"', false)
            ->assertSee('data-name-en="Nuseirat"', false);
    }

    public function test_navbar_area_links_point_to_the_home_page_with_area_param(): void
    {
        $region = Regions::create([
            'region_slug' => 'central-gaza',
            'region_name_en' => 'Central Gaza',
            'region_name_ar' => 'وسط غزة',
            'region_sort_order' => 1,
        ]);

        Area::create([
            'area_slug' => 'deir-al-balah',
            'area_name_en' => 'Deir al-Balah',
            'area_name_ar' => 'دير البلح',
            'area_sort_order' => 1,
            'region_id' => $region->id,
        ]);

        $link = route('gazamarket.home', ['area' => 'Deir al-Balah']);

        $this->get('/gazamarket/index')->assertOk()->assertSee('href="'.e($link).'"', false);
        $this->get('/gazamarket/home')->assertOk()->assertSee('data-name-ar="دير البلح"', false);
    }

    public function test_names_are_escaped_in_the_navbar(): void
    {
        Regions::create([
            'region_slug' => 'xss',
            'region_name_en' => 'Region "><script>alert(1)</script>',
            'region_name_ar' => 'منطقة',
            'region_sort_order' => 1,
        ]);

        $this->get('/gazamarket/index')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}