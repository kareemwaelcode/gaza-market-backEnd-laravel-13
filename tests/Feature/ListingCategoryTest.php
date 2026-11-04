<?php

namespace Tests\Feature;

use App\Models\ListingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCategoryTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/cms/admins/listing_category';

    private function category(array $overrides = []): ListingCategory
    {
        return ListingCategory::create($overrides + [
            'listing_category_slug' => 'electronics',
            'listing_category_name_en' => 'Electronics',
            'listing_category_name_ar' => 'إلكترونيات',
            'listing_category_is_active' => true,
            'listing_category_sort_order' => 1,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'listing_category_name_en' => 'Furniture',
            'listing_category_name_ar' => 'أثاث',
            'listing_category_is_active' => 1,
            'listing_category_sort_order' => 2,
        ];
    }

    public function test_index_create_and_edit_pages_load(): void
    {
        $category = $this->category();

        $this->get(self::BASE)->assertOk()->assertSee('Electronics');
        $this->get(self::BASE.'/create')->assertOk();
        $this->get(self::BASE."/{$category->id}/edit")->assertOk();
    }

    public function test_index_is_ordered_by_sort_order(): void
    {
        $this->category(['listing_category_slug' => 'b', 'listing_category_name_en' => 'Second', 'listing_category_sort_order' => 2]);
        $this->category(['listing_category_slug' => 'a', 'listing_category_name_en' => 'First', 'listing_category_sort_order' => 1]);

        $this->get(self::BASE)->assertOk()->assertSeeInOrder(['First', 'Second']);
    }

    public function test_store_creates_category_with_generated_slug(): void
    {
        $this->postJson(self::BASE, $this->payload())->assertOk();

        $this->assertDatabaseHas('listing_categories', [
            'listing_category_slug' => 'furniture',
            'listing_category_name_en' => 'Furniture',
            'listing_category_is_active' => 1,
            'listing_category_sort_order' => 2,
        ]);
    }

    public function test_slug_stays_unique_for_the_same_name(): void
    {
        $this->postJson(self::BASE, $this->payload())->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertOk();

        $this->assertDatabaseHas('listing_categories', ['listing_category_slug' => 'furniture']);
        $this->assertDatabaseHas('listing_categories', ['listing_category_slug' => 'furniture-2']);
    }

    public function test_store_ignores_a_client_supplied_slug(): void
    {
        $this->postJson(self::BASE, $this->payload(['listing_category_slug' => 'hacked']))->assertOk();

        $this->assertDatabaseMissing('listing_categories', ['listing_category_slug' => 'hacked']);
    }

    public function test_sort_order_is_optional(): void
    {
        $this->postJson(self::BASE, $this->payload(['listing_category_sort_order' => '']))->assertOk();

        $this->assertNull(ListingCategory::first()->listing_category_sort_order);
    }

    public function test_names_accept_up_to_100_characters(): void
    {
        $name = str_repeat('a', 100);

        $this->postJson(self::BASE, $this->payload([
            'listing_category_name_en' => $name,
            'listing_category_name_ar' => str_repeat('ب', 100),
        ]))->assertOk();

        $this->assertSame($name, ListingCategory::first()->listing_category_name_en);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $invalid = [
            ['listing_category_name_en' => str_repeat('a', 101)],
            ['listing_category_name_ar' => str_repeat('ب', 101)],
            ['listing_category_name_en' => ''],
            ['listing_category_name_ar' => ''],
            ['listing_category_name_en' => '!!!'],
            ['listing_category_is_active' => 'maybe'],
            ['listing_category_sort_order' => -1],
            ['listing_category_sort_order' => 32768],
        ];

        foreach ($invalid as $overrides) {
            $this->postJson(self::BASE, $this->payload($overrides))
                ->assertStatus(400)
                ->assertJsonPath('icon', 'error');
        }

        $this->assertSame(0, ListingCategory::count());
    }

    public function test_update_changes_editable_fields_but_not_the_slug(): void
    {
        $category = $this->category();

        $this->postJson("/cms/admins/listing_category_update/{$category->id}", $this->payload([
            'listing_category_slug' => 'hacked',
        ]))->assertOk()->assertJsonStructure(['redirect']);

        $category->refresh();
        $this->assertSame('electronics', $category->listing_category_slug);
        $this->assertSame('Furniture', $category->listing_category_name_en);
        $this->assertSame('أثاث', $category->listing_category_name_ar);
        $this->assertSame(2, $category->listing_category_sort_order);
    }

    public function test_category_can_be_deactivated_and_reactivated(): void
    {
        $category = $this->category();

        $this->postJson("/cms/admins/listing_category_update/{$category->id}", $this->payload(['listing_category_is_active' => 0]))->assertOk();
        $this->assertFalse($category->fresh()->listing_category_is_active);
        $this->assertSame(0, ListingCategory::active()->count());

        $this->postJson("/cms/admins/listing_category_update/{$category->id}", $this->payload(['listing_category_is_active' => 1]))->assertOk();
        $this->assertSame(1, ListingCategory::active()->count());
    }

    public function test_update_of_unknown_category_returns_404(): void
    {
        $this->postJson('/cms/admins/listing_category_update/999', $this->payload())->assertNotFound();
    }

      public function test_category_is_deleted_from_the_database(): void
    {
        $category = $this->category();

        $this->deleteJson(self::BASE."/{$category->id}")
            ->assertOk()
            ->assertJsonPath('icon', 'success');

        $this->assertDatabaseMissing('listing_categories', ['id' => $category->id]);
        $this->assertSame(0, ListingCategory::count());
    }

    public function test_deleting_one_category_keeps_the_others(): void
    {
        $first = $this->category();
        $second = $this->category(['listing_category_slug' => 'furniture', 'listing_category_name_en' => 'Furniture']);

        $this->deleteJson(self::BASE."/{$first->id}")->assertOk();

        $this->assertDatabaseMissing('listing_categories', ['id' => $first->id]);
        $this->assertDatabaseHas('listing_categories', ['id' => $second->id]);
    }

    public function test_deleting_unknown_category_returns_404(): void
    {
        $this->deleteJson(self::BASE.'/999')->assertNotFound();
    }

    public function test_index_shows_a_delete_button_per_category(): void
    {
        $category = $this->category();

        $this->get(self::BASE)->assertOk()->assertSee("performDestroy({$category->id}, this)", false);
    }
}