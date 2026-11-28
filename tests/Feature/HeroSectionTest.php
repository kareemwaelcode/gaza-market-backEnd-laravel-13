<?php

namespace Tests\Feature;

use App\Models\HeroSection;
use App\Models\Listing;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMarketData;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class HeroSectionTest extends TestCase
{
    use BuildsMarketData, BuildsProductPrices {
        BuildsProductPrices::makeUser insteadof BuildsMarketData;
    }
    use RefreshDatabase;

    private const FIELDS = [
        'eyebrow', 'title', 'subtitle', 'location',
        'products_label', 'prices_label', 'listings_label', 'places_label', 'ticker_label',
    ];

    private function payload(string $suffix = ''): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data["hero_section_{$field}_ar"] = "عربي {$field}{$suffix}";
            $data["hero_section_{$field}_en"] = "English {$field}{$suffix}";
        }

        return $data;
    }

    private function hero(): HeroSection
    {
        return HeroSection::create($this->payload());
    }

    public function test_index_and_edit_pages_load(): void
    {
        $hero = $this->hero();

        $this->get('/cms/admins/hero_section')->assertOk();
        $this->get("/cms/admins/hero_section/{$hero->id}/edit")->assertOk();
    }

    public function test_update_changes_all_fields(): void
    {
        $hero = $this->hero();

        $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $this->payload(' v2'))
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $hero->refresh();
        $this->assertSame('English title v2', $hero->hero_section_title_en);
        $this->assertSame('عربي ticker_label v2', $hero->hero_section_ticker_label_ar);
    }

    public function test_every_field_is_required(): void
    {
        $hero = $this->hero();

        foreach (array_keys($this->payload()) as $column) {
            $data = $this->payload('x');
            unset($data[$column]);

            $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $data)
                ->assertStatus(400);
        }

        $this->assertSame('English title', $hero->fresh()->hero_section_title_en);
    }

    public function test_length_limits_match_the_columns(): void
    {
        $hero = $this->hero();

        $data = $this->payload('x');
        $data['hero_section_products_label_en'] = str_repeat('a', 51);
        $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $data)->assertStatus(400);

        $data = $this->payload('x');
        $data['hero_section_subtitle_ar'] = str_repeat('م', 256);
        $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $data)->assertStatus(400);

        $data = $this->payload('x');
        $data['hero_section_subtitle_ar'] = str_repeat('م', 255);
        $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $data)->assertOk();
    }

    public function test_english_text_must_contain_latin_characters(): void
    {
        $hero = $this->hero();

        $data = $this->payload('x');
        $data['hero_section_title_en'] = 'عنوان عربي';

        $this->postJson("/cms/admins/hero_section_update/{$hero->id}", $data)->assertStatus(400);
    }

    public function test_unknown_hero_returns_404(): void
    {
        $this->postJson('/cms/admins/hero_section_update/999', $this->payload())->assertNotFound();
    }

    public function test_public_hero_endpoint_returns_section_stats_and_ticker(): void
    {
        $this->hero();

        $response = $this->getJson('/api/hero')
            ->assertOk()
            ->assertJsonStructure([
                'section' => ['hero_section_title_ar', 'hero_section_title_en', 'hero_section_ticker_label_en'],
                'stats' => ['products', 'prices', 'listings', 'places'],
                'ticker',
            ])
            ->assertJsonPath('section.hero_section_title_en', 'English title');

        $this->assertArrayNotHasKey('id', $response->json('section'));
        $this->assertArrayNotHasKey('created_at', $response->json('section'));
    }

    public function test_public_hero_stats_count_only_public_records(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();

        $active = $this->makeProduct($lookups, $user, 'active');
        $hidden = $this->makeProduct($lookups, $user, 'hidden');
        $this->makeProduct($lookups, $user, 'pending');

        $this->makePrice($active, $lookups, $user, 'approved');
        $this->makePrice($active, $lookups, $user, 'approved');
        $this->makePrice($active, $lookups, $user, 'pending');
        $this->makePrice($hidden, $lookups, $user, 'approved');

        $this->makeListing($user, Listing::STATUS_APPROVED);
        $this->makeListing($user, Listing::STATUS_APPROVED);
        $this->makeListing($user, Listing::STATUS_PENDING);
        $this->makeListing($user, Listing::STATUS_SOLD);

        $this->makePlace($user, Place::STATUS_APPROVED);
        $this->makePlace($this->makeUser(), Place::STATUS_PENDING);

        $this->getJson('/api/hero')
            ->assertOk()
            ->assertJsonPath('stats.products', 1)
            ->assertJsonPath('stats.prices', 2)
            ->assertJsonPath('stats.listings', 2)
            ->assertJsonPath('stats.places', 1);
    }

    public function test_public_hero_ticker_uses_the_field_names_the_front_end_reads(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user, 'active', [
            'product_name_ar' => 'سكر',
            'product_name_en' => 'Sugar',
        ]);
        $this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 8.5]);

        $response = $this->getJson('/api/hero')->assertOk()->assertJsonCount(1, 'ticker');

        $this->assertSame(['name_ar', 'name_en', 'price'], array_keys($response->json('ticker.0')));
        $this->assertSame('سكر', $response->json('ticker.0.name_ar'));
        $this->assertSame('Sugar', $response->json('ticker.0.name_en'));
    }

    public function test_public_hero_with_empty_database_returns_null_section_and_zero_stats(): void
    {
        $this->getJson('/api/hero')
            ->assertOk()
            ->assertJsonPath('section', null)
            ->assertJsonPath('stats.products', 0)
            ->assertJsonPath('stats.prices', 0)
            ->assertJsonPath('stats.listings', 0)
            ->assertJsonPath('stats.places', 0)
            ->assertJsonPath('ticker', []);
    }

    public function test_public_hero_endpoint_opens_no_session(): void
    {
        config(['session.driver' => 'database']);

        $response = $this->getJson('/api/hero')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
    }
}