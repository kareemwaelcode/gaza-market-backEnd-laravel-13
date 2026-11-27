<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Regions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_and_market_details_pages_are_served(): void
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

        $this->get('/gazamarket/market')
            ->assertOk()
            ->assertSee('data-name-ar="النصيرات"', false);

        $this->get('/gazamarket/market-details?id=1')
            ->assertOk()
            ->assertSee('data-name-ar="النصيرات"', false);
    }

    public function test_market_pages_have_named_routes(): void
    {
        $this->assertSame(url('/gazamarket/market'), route('gazamarket.market'));
        $this->assertSame(url('/gazamarket/market-details'), route('gazamarket.market-details'));
    }

    public function test_market_blade_links_no_longer_point_to_html_files(): void
    {
        $this->get('/gazamarket/market-details?id=1')
            ->assertOk()
            ->assertDontSee('href="market.html"', false);
    }
}