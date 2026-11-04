<?php

namespace Tests\Feature;

use App\Models\HeroSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroSectionTest extends TestCase
{
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
}