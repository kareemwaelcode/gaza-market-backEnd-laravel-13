<?php

namespace Tests\Feature;

use App\Models\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationItemTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'navigation_item_name_en' => 'Home',
            'navigation_item_name_ar' => 'الرئيسية',
            'navigation_item_icon' => 'house',
            'navigation_item_url' => '/home',
            'navigation_item_sort_order' => 1,
            'navigation_item_is_active' => 1,
            'navigation_item_is_mobile' => 1,
            'navigation_item_is_desktop' => 1,
            'navigation_item_is_coming_soon' => 0,
        ];
    }

    public function test_pages_load(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();
        $item = NavigationItem::first();

        $this->get('/cms/admins/navigation_item')->assertOk();
        $this->get('/cms/admins/navigation_item/create')->assertOk();
        $this->get("/cms/admins/navigation_item/{$item->id}/edit")->assertOk();
    }

    public function test_store_generates_slug_from_english_name(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_name_en' => 'Price Compare']))
            ->assertOk();

        $this->assertSame('price-compare', NavigationItem::first()->navigation_item_slug);
    }

    public function test_same_name_gets_a_numbered_slug(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();

        $this->assertSame(
            ['home', 'home-2', 'home-3'],
            NavigationItem::orderBy('id')->pluck('navigation_item_slug')->all()
        );
    }

    public function test_slug_collision_during_save_is_retried_not_a_500(): void
    {
        // Simulate a concurrent request that takes the same slug between the
        // "does it exist?" check and the INSERT.
        $fired = false;
        NavigationItem::creating(function (NavigationItem $item) use (&$fired) {
            if (! $fired) {
                $fired = true;
                DB::table('navigation_items')->insert([
                    'navigation_item_slug' => $item->navigation_item_slug,
                    'navigation_item_name_ar' => 'x',
                    'navigation_item_name_en' => 'x',
                    'navigation_item_icon' => 'x',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();

        $this->assertSame(
            ['home', 'home-2'],
            NavigationItem::orderBy('id')->pluck('navigation_item_slug')->all()
        );
    }

    public function test_missing_sort_order_is_stored_as_zero_not_null(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_sort_order' => '']))
            ->assertOk();

        $this->assertSame(0, NavigationItem::first()->navigation_item_sort_order);
    }

    public function test_empty_url_is_stored_as_null(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_url' => '']))
            ->assertOk();

        $this->assertNull(NavigationItem::first()->navigation_item_url);
    }

    public function test_dangerous_or_invalid_urls_are_rejected(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,x', 'ftp://example.com', 'has space', '//evil.com'] as $url) {
            $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_url' => $url]))
                ->assertStatus(400);
        }

        $this->assertSame(0, NavigationItem::count());
    }

    public function test_valid_urls_are_accepted(): void
    {
        foreach (['#', '/products', 'products/list?x=1', 'https://example.com/a?b=1'] as $url) {
            $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_url' => $url]))
                ->assertOk();
        }

        $this->assertSame(4, NavigationItem::count());
    }

    public function test_invalid_icon_and_values_are_rejected(): void
    {
        foreach ([
            ['navigation_item_icon' => 'Bad Icon!'],
            ['navigation_item_icon' => ''],
            ['navigation_item_name_en' => 'اسم عربي'],
            ['navigation_item_name_en' => str_repeat('a', 46)],
            ['navigation_item_sort_order' => 300],
            ['navigation_item_is_active' => 'maybe'],
        ] as $bad) {
            $this->postJson('/cms/admins/navigation_item', $this->payload($bad))->assertStatus(400);
        }

        $this->assertSame(0, NavigationItem::count());
    }

    public function test_update_keeps_the_slug(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();
        $item = NavigationItem::first();

        $this->postJson("/cms/admins/navigation_item_update/{$item->id}", $this->payload([
            'navigation_item_name_en' => 'Totally Different',
            'navigation_item_is_coming_soon' => 1,
        ]))->assertOk()->assertJsonStructure(['redirect']);

        $item->refresh();
        $this->assertSame('home', $item->navigation_item_slug);
        $this->assertSame('Totally Different', $item->navigation_item_name_en);
        $this->assertTrue($item->navigation_item_is_coming_soon);
    }

    public function test_destroy_deletes_the_item(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload())->assertOk();
        $item = NavigationItem::first();

        $this->deleteJson("/cms/admins/navigation_item/{$item->id}")->assertOk();

        $this->assertSame(0, NavigationItem::count());
        $this->deleteJson("/cms/admins/navigation_item/{$item->id}")->assertNotFound();
    }

    public function test_scopes_filter_active_and_order_by_sort_order(): void
    {
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_name_en' => 'Second', 'navigation_item_sort_order' => 2]));
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_name_en' => 'First', 'navigation_item_sort_order' => '']));
        $this->postJson('/cms/admins/navigation_item', $this->payload(['navigation_item_name_en' => 'Hidden', 'navigation_item_sort_order' => 3, 'navigation_item_is_active' => 0]));

        $this->assertSame(
            ['first', 'second'],
            NavigationItem::active()->ordered()->pluck('navigation_item_slug')->all()
        );
    }
}