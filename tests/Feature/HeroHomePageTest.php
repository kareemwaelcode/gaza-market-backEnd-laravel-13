<?php

namespace Tests\Feature;

use App\Models\HeroSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class HeroHomePageTest extends TestCase
{
    use BuildsMarketData, BuildsProductPrices {
        BuildsProductPrices::makeUser insteadof BuildsMarketData;
    }
    use RefreshDatabase;

    private const FIELDS = [
        'eyebrow', 'title', 'subtitle', 'location',
        'products_label', 'prices_label', 'listings_label', 'places_label', 'ticker_label',
    ];

    public function test_home_page_embeds_hero_columns_from_the_table(): void
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data["hero_section_{$field}_ar"] = "عربي {$field}";
            $data["hero_section_{$field}_en"] = "English {$field}";
        }
        HeroSection::create($data);

        $this->get('/gazamarket/home')
            ->assertOk()
            ->assertSee('window.GM_HERO_SECTION', false)
            ->assertSee('English title', false)
            ->assertDontSee('created_at', false);
    }

    public function test_home_page_embeds_the_ticker_with_the_names_the_front_end_reads(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $this->makeProduct($lookups, $user, 'active', [
            'product_name_ar' => 'سكر',
            'product_name_en' => 'Sugar',
            'product_price' => 8.5,
        ]);

        $this->get('/gazamarket/home')
            ->assertOk()
            ->assertSee('window.GM_HERO_TICKER', false)
            ->assertSee('"name_en":"Sugar"', false)
            ->assertSee('"price":"8.50"', false);
    }

    public function test_home_page_works_when_hero_table_and_ticker_are_empty(): void
    {
        $this->get('/gazamarket/home')
            ->assertOk()
            ->assertSee('window.GM_HERO_SECTION = null', false)
            ->assertSee('window.GM_HERO_TICKER = []', false);
    }
}