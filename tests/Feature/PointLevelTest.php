<?php

namespace Tests\Feature;

use App\Models\PointLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointLevelTest extends TestCase
{
    use RefreshDatabase;

    private function level(string $key, int $min, int $sort = 0): PointLevel
    {
        return PointLevel::create([
            'point_level_key' => $key,
            'point_level_name_en' => ucfirst($key),
            'point_level_name_ar' => 'مستوى '.$key,
            'point_level_min_points' => $min,
            'point_level_sort_order' => $sort,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'point_level_name_en' => 'Renamed',
            'point_level_name_ar' => 'اسم جديد',
            'point_level_min_points' => 100,
            'point_level_sort_order' => 1,
        ];
    }

    public function test_index_and_edit_pages_load(): void
    {
        $level = $this->level('beginner', 0);

        $this->get('/cms/admins/point_level')->assertOk();
        $this->get("/cms/admins/point_level/{$level->id}/edit")->assertOk();
    }

    public function test_for_points_returns_highest_matching_level(): void
    {
        $this->level('beginner', 0);
        $this->level('active', 100, 1);
        $this->level('expert', 500, 2);

        $this->assertSame('beginner', PointLevel::forPoints(0)->point_level_key);
        $this->assertSame('beginner', PointLevel::forPoints(99)->point_level_key);
        $this->assertSame('active', PointLevel::forPoints(100)->point_level_key);
        $this->assertSame('active', PointLevel::forPoints(230)->point_level_key);
        $this->assertSame('expert', PointLevel::forPoints(10000)->point_level_key);
    }

    public function test_for_points_is_null_without_a_base_level(): void
    {
        $this->level('active', 100);

        $this->assertNull(PointLevel::forPoints(0));
    }

    public function test_update_changes_editable_fields_and_key_is_read_only(): void
    {
        $this->level('beginner', 0);
        $level = $this->level('active', 100, 1);

        $this->postJson("/cms/admins/point_level_update/{$level->id}", $this->payload([
            'point_level_min_points' => 150,
            'point_level_key' => 'hacked',
        ]))->assertOk()->assertJsonStructure(['redirect']);

        $level->refresh();
        $this->assertSame('Renamed', $level->point_level_name_en);
        $this->assertSame(150, $level->point_level_min_points);
        $this->assertSame('active', $level->point_level_key);
    }

    public function test_base_level_must_keep_zero_min_points(): void
    {
        $base = $this->level('beginner', 0);

        $this->postJson("/cms/admins/point_level_update/{$base->id}", $this->payload(['point_level_min_points' => 50]))
            ->assertStatus(400);

        $this->assertSame(0, $base->fresh()->point_level_min_points);
    }

    public function test_base_level_can_still_be_renamed(): void
    {
        $base = $this->level('beginner', 0);

        $this->postJson("/cms/admins/point_level_update/{$base->id}", $this->payload(['point_level_min_points' => 0]))
            ->assertOk();

        $this->assertSame('Renamed', $base->fresh()->point_level_name_en);
    }

    public function test_duplicate_min_points_is_rejected(): void
    {
        $this->level('beginner', 0);
        $level = $this->level('active', 100, 1);

        $this->postJson("/cms/admins/point_level_update/{$level->id}", $this->payload(['point_level_min_points' => 0]))
            ->assertStatus(400);

        $this->assertSame(100, $level->fresh()->point_level_min_points);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $level = $this->level('beginner', 0);

        foreach ([
            ['point_level_min_points' => -1],
            ['point_level_min_points' => 'abc'],
            ['point_level_sort_order' => 200],
            ['point_level_name_en' => ''],
            ['point_level_name_en' => 'اسم عربي فقط'],
            ['point_level_name_ar' => str_repeat('م', 101)],
        ] as $bad) {
            $this->postJson("/cms/admins/point_level_update/{$level->id}", $this->payload($bad + ['point_level_min_points' => 0]))
                ->assertStatus(400);
        }
    }
}