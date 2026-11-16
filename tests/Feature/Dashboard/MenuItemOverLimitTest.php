<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardMenuItem;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MenuItemOverLimitTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/menu-items';

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $this->area = new Area;
        $this->area->area_slug = 'a-'.Str::random(6);
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    private function owner(string $plan, $expiresAt = null): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create();

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = Place::STATUS_APPROVED;
        $place->place_plan = $plan;
        $place->place_plan_expires_at = $expiresAt;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    /** @return array<int, DashboardMenuItem> مرتبة من الأقدم للأحدث */
    private function items(User $owner, int $count): array
    {
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $item = new DashboardMenuItem(['name' => "صنف $i", 'price' => 10]);
            $item->place_id = $this->placeOf($owner)->id;
            $item->save();
            $items[] = $item;
        }

        return $items;
    }

    public function test_expired_plan_marks_items_over_the_free_limit_and_keeps_the_oldest(): void
    {
        $user = $this->owner('paid', now()->subDay());
        $items = $this->items($user, 15);

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.used', 15)
            ->assertJsonPath('meta.overLimitCount', 5);

        $flags = collect($response->json('data'))->pluck('overLimit', 'id');

        foreach (array_slice($items, 0, 10) as $item) {
            $this->assertFalse($flags[(string) $item->id], "الصنف {$item->id} يجب أن يبقى ظاهراً");
        }

        foreach (array_slice($items, 10) as $item) {
            $this->assertTrue($flags[(string) $item->id], "الصنف {$item->id} يجب أن يكون فوق الحد");
        }
    }

    public function test_renewal_restores_everything_without_data_changes(): void
    {
        $user = $this->owner('paid', now()->subDay());
        $this->items($user, 15);

        $this->actingAs($user)->getJson(self::URL)->assertJsonPath('meta.overLimitCount', 5);

        $this->placeOf($user)->forceFill(['place_plan_expires_at' => now()->addMonth()])->save();

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk()
            ->assertJsonPath('meta.limit', null)
            ->assertJsonPath('meta.overLimitCount', 0);

        $this->assertNotContains(true, collect($response->json('data'))->pluck('overLimit')->all());
        $this->assertSame(15, DashboardMenuItem::count());
    }

    public function test_active_paid_and_free_within_limit_have_no_over_limit_items(): void
    {
        $paid = $this->owner('paid', now()->addMonth());
        $this->items($paid, 12);
        $this->actingAs($paid)->getJson(self::URL)->assertJsonPath('meta.overLimitCount', 0);

        $free = $this->owner('free');
        $this->items($free, 10);
        $this->actingAs($free)->getJson(self::URL)->assertJsonPath('meta.overLimitCount', 0);
    }

    public function test_over_limit_items_can_be_shown_edited_and_deleted_and_the_flag_follows(): void
    {
        $user = $this->owner('paid', now()->subDay());
        $items = $this->items($user, 12);
        $oldest = $items[0];
        $newest = $items[11];

        $this->actingAs($user)->getJson(self::URL.'/'.$newest->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);

        $this->actingAs($user)->getJson(self::URL.'/'.$oldest->id)
            ->assertOk()->assertJsonPath('data.overLimit', false);

        $this->actingAs($user)->patchJson(self::URL.'/'.$newest->id, ['name' => 'معدّل'])
            ->assertOk()->assertJsonPath('data.name', 'معدّل')->assertJsonPath('data.overLimit', true);

        // حذف أقدم صنف: الصنف الحادي عشر يدخل ضمن الباقة، والأخير يبقى فوق الحد.
        $this->actingAs($user)->deleteJson(self::URL.'/'.$oldest->id)->assertNoContent();

        $this->actingAs($user)->getJson(self::URL.'/'.$items[10]->id)
            ->assertOk()->assertJsonPath('data.overLimit', false);
        $this->actingAs($user)->getJson(self::URL.'/'.$newest->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);
        $this->actingAs($user)->getJson(self::URL)->assertJsonPath('meta.overLimitCount', 1);

        $this->actingAs($user)->deleteJson(self::URL.'/'.$newest->id)->assertNoContent();
        $this->actingAs($user)->getJson(self::URL)->assertJsonPath('meta.overLimitCount', 0);
    }

    public function test_expired_owner_cannot_add_items_until_back_under_the_limit(): void
    {
        $user = $this->owner('paid', now()->subDay());
        $items = $this->items($user, 11);

        $this->actingAs($user)->postJson(self::URL, ['name' => 'جديد'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED');

        $this->actingAs($user)->deleteJson(self::URL.'/'.$items[10]->id)->assertNoContent();

        // عند 10 أصناف (الحد المجاني) ما زال ممنوعاً: الحد نفسه يُعدّ ممتلئاً.
        $this->actingAs($user)->postJson(self::URL, ['name' => 'جديد'])->assertStatus(403);
    }
}