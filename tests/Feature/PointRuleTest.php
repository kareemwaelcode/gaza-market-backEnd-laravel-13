<?php

namespace Tests\Feature;

use App\Models\PointRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointRuleTest extends TestCase
{
    use RefreshDatabase;

    private function rule(array $overrides = []): PointRule
    {
        return PointRule::create($overrides + [
            'point_rule_key' => 'add_price',
            'point_rule_name_en' => 'Add price',
            'point_rule_name_ar' => 'إضافة سعر',
            'point_rule_points' => 10,
            'point_rule_daily_limit' => 5,
            'point_rule_is_active' => true,
            'point_rule_sort_order' => 1,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'point_rule_name_en' => 'Add price v2',
            'point_rule_name_ar' => 'إضافة سعر 2',
            'point_rule_points' => 20,
            'point_rule_daily_limit' => 3,
            'point_rule_is_active' => 1,
            'point_rule_sort_order' => 2,
        ];
    }

    public function test_index_and_edit_pages_load(): void
    {
        $rule = $this->rule();

        $this->get('/cms/admins/point_rule')->assertOk();
        $this->get("/cms/admins/point_rule/{$rule->id}/edit")->assertOk();
    }

    public function test_update_changes_editable_fields(): void
    {
        $rule = $this->rule();

        $this->postJson("/cms/admins/point_rule_update/{$rule->id}", $this->payload())
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $rule->refresh();
        $this->assertSame('Add price v2', $rule->point_rule_name_en);
        $this->assertSame(20, $rule->point_rule_points);
        $this->assertSame(3, $rule->point_rule_daily_limit);
    }

    public function test_key_is_read_only(): void
    {
        $rule = $this->rule();

        $this->postJson("/cms/admins/point_rule_update/{$rule->id}", $this->payload(['point_rule_key' => 'hacked']))
            ->assertOk();

        $this->assertSame('add_price', $rule->fresh()->point_rule_key);
    }

    public function test_points_can_be_negative(): void
    {
        $rule = $this->rule();

        $this->postJson("/cms/admins/point_rule_update/{$rule->id}", $this->payload(['point_rule_points' => -15]))
            ->assertOk();

        $this->assertSame(-15, $rule->fresh()->point_rule_points);
    }

    public function test_empty_daily_limit_means_no_limit(): void
    {
        $rule = $this->rule();

        $this->postJson("/cms/admins/point_rule_update/{$rule->id}", $this->payload(['point_rule_daily_limit' => '']))
            ->assertOk();

        $this->assertNull($rule->fresh()->point_rule_daily_limit);
    }

    public function test_out_of_range_values_are_rejected(): void
    {
        $rule = $this->rule();

        foreach ([
            ['point_rule_points' => 40000],
            ['point_rule_points' => 'abc'],
            ['point_rule_daily_limit' => 0],
            ['point_rule_daily_limit' => 70000],
            ['point_rule_sort_order' => 200],
            ['point_rule_name_en' => str_repeat('a', 101)],
            ['point_rule_name_en' => 'اسم عربي فقط'],
            ['point_rule_name_ar' => ''],
        ] as $bad) {
            $this->postJson("/cms/admins/point_rule_update/{$rule->id}", $this->payload($bad))
                ->assertStatus(400);
        }

        $this->assertSame(10, $rule->fresh()->point_rule_points);
    }

    public function test_unknown_rule_returns_404(): void
    {
        $this->postJson('/cms/admins/point_rule_update/999', $this->payload())->assertNotFound();
    }
}