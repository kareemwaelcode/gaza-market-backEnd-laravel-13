<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardReservation;
use App\Models\DashboardTable;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/reservations';

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

    private function owner(string $type = 'cafe', string $plan = 'free', string $status = Place::STATUS_APPROVED): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create();

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => $type,
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
            'store_category_id' => null,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->place_plan = $plan;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    private function table(User $owner, string $status = 'available'): DashboardTable
    {
        $table = new DashboardTable(['name' => 'طاولة 1', 'seats' => 4, 'status' => $status]);
        $table->place_id = $this->placeOf($owner)->id;
        $table->save();

        return $table;
    }

    private function reservation(User $owner, string $status = 'pending', ?DashboardTable $table = null, string $name = 'أحمد'): DashboardReservation
    {
        $r = new DashboardReservation([
            'name' => $name,
            'country_code' => '970',
            'whatsapp' => '599123456',
            'reserved_at' => Carbon::parse('2026-11-02T17:00:00Z'),
            'people_count' => 4,
            'note' => '',
        ]);
        $r->place_id = $this->placeOf($owner)->id;
        $r->dashboard_table_id = $table?->id;
        $r->status = $status;
        $r->save();

        return $r;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->getJson(self::URL.'/summary')->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['status' => 'confirmed'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->getJson(self::URL.'/summary')->assertStatus(403);
    }

    public function test_unsupported_place_types_get_404_on_all_routes(): void
    {
        foreach (['store', 'restaurant', 'workspace'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            $this->actingAs($user)->getJson(self::URL.'/summary')->assertStatus(404);
            // 404 يسبق التحقق من المدخلات.
            $this->actingAs($user)->patchJson(self::URL.'/1', [])->assertStatus(404);
            $this->actingAs($user)->deleteJson(self::URL.'/1')->assertStatus(404);
        }
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_reservations_newest_first_with_contract_shape(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $table = $this->table($mine);
        $first = $this->reservation($mine, 'pending', $table, 'الأولى');
        $second = $this->reservation($mine, 'confirmed', null, 'الثانية');
        $this->reservation($other, 'pending', null, 'لغيري');

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $second->id)
            ->assertJsonPath('data.1.id', (string) $first->id)
            ->assertJsonPath('data.1.name', 'الأولى')
            ->assertJsonPath('data.1.countryCode', '970')
            ->assertJsonPath('data.1.whatsapp', '599123456')
            ->assertJsonPath('data.1.datetime', '2026-11-02T17:00:00.000Z')
            ->assertJsonPath('data.1.peopleCount', 4)
            ->assertJsonPath('data.1.tableId', (string) $table->id)
            ->assertJsonPath('data.1.table.name', 'طاولة 1')
            ->assertJsonPath('data.1.tableDeleted', false)
            ->assertJsonPath('data.1.status', 'pending')
            ->assertJsonPath('data.0.table', null)
            ->assertJsonPath('data.0.tableDeleted', false)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_index_shows_deleted_table_flag(): void
    {
        $user = $this->owner();
        $table = $this->table($user);
        $this->reservation($user, 'pending', $table);
        $table->delete();

        $this->actingAs($user)->getJson(self::URL)->assertOk()
            ->assertJsonPath('data.0.tableDeleted', true)
            ->assertJsonPath('data.0.table.name', 'طاولة 1');
    }

    public function test_index_filters_by_status_and_name(): void
    {
        $user = $this->owner();
        $this->reservation($user, 'pending', null, 'أحمد علي');
        $this->reservation($user, 'confirmed', null, 'سارة');
        $this->reservation($user, 'cancelled', null, 'أحمد خالد');

        $this->actingAs($user)->getJson(self::URL.'?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson(self::URL.'?q=أحمد')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->actingAs($user)->getJson(self::URL.'?q=أحمد&status=cancelled')->assertOk()->assertJsonCount(1, 'data');
        // علامات LIKE تُعامل كنص عادي.
        $this->actingAs($user)->getJson(self::URL.'?q=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_paginates_with_cursor_without_duplicates(): void
    {
        $user = $this->owner('cafe', 'paid');
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $ids[] = (string) $this->reservation($user, 'pending', null, "حجز $i")->id;
        }

        $first = $this->actingAs($user)->getJson(self::URL.'?limit=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true)->assertJsonPath('meta.total', 5);

        $seen = array_column($first->json('data'), 'id');
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        while ($cursor !== null) {
            $page = $this->actingAs($user)->getJson(self::URL.'?limit=2&cursor='.urlencode($cursor))->assertOk();
            $seen = array_merge($seen, array_column($page->json('data'), 'id'));
            $cursor = $page->json('meta.next_cursor');
        }

        $this->assertSame(array_reverse($ids), $seen);
    }

    public function test_index_validates_query_params(): void
    {
        $user = $this->owner();

        foreach (['status=done', 'limit=0', 'limit=51', 'limit=abc', 'q='.str_repeat('a', 81)] as $query) {
            $this->actingAs($user)->getJson(self::URL.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }
    }

    // ---------- الملخص ----------

    public function test_summary_counts_and_limits(): void
    {
        $free = $this->owner();
        $this->reservation($free, 'pending');
        $this->reservation($free, 'pending');
        $this->reservation($free, 'confirmed');
        $this->reservation($free, 'cancelled');
        $this->reservation($this->owner(), 'pending');

        $this->actingAs($free)->getJson(self::URL.'/summary')->assertOk()
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.confirmed', 1)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.limit', 5)
            ->assertJsonPath('data.used', 4)
            ->assertJsonPath('data.hidden', 0);

        $this->actingAs($this->owner('cafe', 'paid'))->getJson(self::URL.'/summary')->assertOk()
            ->assertJsonPath('data.limit', null)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.pending', 0);
    }

    public function test_summary_used_still_counts_soft_deleted_reservations(): void
    {
        $user = $this->owner();
        $this->reservation($user, 'cancelled')->delete();
        $this->reservation($user, 'pending');

        $this->actingAs($user)->getJson(self::URL.'/summary')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.used', 2);
    }

    // ---------- تغيير الحالة ----------

    public function test_confirming_marks_table_occupied(): void
    {
        $user = $this->owner();
        $table = $this->table($user);
        $r = $this->reservation($user, 'pending', $table);

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'confirmed'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.tableId', (string) $table->id);

        $this->assertSame('confirmed', $r->refresh()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_confirming_without_table_only_changes_the_reservation(): void
    {
        $user = $this->owner();
        $r = $this->reservation($user, 'pending');

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'confirmed'])->assertOk();

        $this->assertSame('confirmed', $r->refresh()->status);
    }

    public function test_confirming_on_occupied_table_returns_409_and_changes_nothing(): void
    {
        $user = $this->owner();
        $table = $this->table($user);
        $a = $this->reservation($user, 'pending', $table);
        $b = $this->reservation($user, 'pending', $table);

        $this->actingAs($user)->patchJson(self::URL.'/'.$a->id, ['status' => 'confirmed'])->assertOk();
        $this->actingAs($user)->patchJson(self::URL.'/'.$b->id, ['status' => 'confirmed'])
            ->assertStatus(409)->assertJsonPath('code', 'table_unavailable');

        $this->assertSame('pending', $b->refresh()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_confirming_on_soft_deleted_table_returns_409(): void
    {
        $user = $this->owner();
        $table = $this->table($user);
        $r = $this->reservation($user, 'pending', $table);
        $table->delete();

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'confirmed'])
            ->assertStatus(409)->assertJsonPath('code', 'table_unavailable');

        $this->assertSame('pending', $r->refresh()->status);
    }

    public function test_cancelling_confirmed_frees_the_table(): void
    {
        $user = $this->owner();
        $table = $this->table($user, 'occupied');
        $r = $this->reservation($user, 'confirmed', $table);

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'cancelled'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('available', $table->refresh()->status);
    }

    public function test_cancelling_pending_leaves_the_table_untouched(): void
    {
        $user = $this->owner();
        $table = $this->table($user, 'occupied');
        $r = $this->reservation($user, 'pending', $table);

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'cancelled'])->assertOk();

        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_cancelling_confirmed_with_deleted_table_still_succeeds(): void
    {
        $user = $this->owner();
        $table = $this->table($user, 'occupied');
        $r = $this->reservation($user, 'confirmed', $table);
        $table->delete();

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => 'cancelled'])->assertOk();

        $this->assertSame('cancelled', $r->refresh()->status);
    }

    public function test_invalid_transitions_return_422_invalid_transition(): void
    {
        $user = $this->owner();

        foreach ([['confirmed', 'confirmed'], ['cancelled', 'confirmed'], ['cancelled', 'cancelled']] as [$from, $to]) {
            $r = $this->reservation($user, $from);

            $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, ['status' => $to])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

            $this->assertSame($from, $r->refresh()->status);
        }
    }

    public function test_patch_validates_status_value(): void
    {
        $user = $this->owner();
        $r = $this->reservation($user);

        foreach ([[], ['status' => 'pending'], ['status' => 'done'], ['status' => ['confirmed']]] as $payload) {
            $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, $payload)
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }
    }

    public function test_patch_cannot_change_other_fields(): void
    {
        $user = $this->owner();
        $other = $this->owner();
        $r = $this->reservation($user);

        $this->actingAs($user)->patchJson(self::URL.'/'.$r->id, [
            'status' => 'cancelled',
            'name' => 'مخترق',
            'place_id' => $this->placeOf($other)->id,
        ])->assertOk();

        $r->refresh();
        $this->assertSame('أحمد', $r->name);
        $this->assertSame($this->placeOf($user)->id, $r->place_id);
    }

    public function test_other_owners_and_unknown_reservations_return_404(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $theirs = $this->reservation($other, 'cancelled');

        $this->actingAs($mine)->patchJson(self::URL.'/'.$theirs->id, ['status' => 'cancelled'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson(self::URL.'/'.$theirs->id)->assertStatus(404);
        $this->actingAs($mine)->patchJson(self::URL.'/999999', ['status' => 'confirmed'])->assertStatus(404);

        $this->assertNotNull($theirs->fresh());
        $this->assertSame('cancelled', $theirs->fresh()->status);
    }

    public function test_cannot_confirm_using_a_table_of_another_place(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $foreignTable = $this->table($other);
        $r = $this->reservation($mine, 'pending', $foreignTable);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$r->id, ['status' => 'confirmed'])->assertStatus(409);

        $this->assertSame('available', $foreignTable->refresh()->status);
    }

    // ---------- الحذف ----------

    public function test_only_cancelled_reservations_can_be_deleted(): void
    {
        $user = $this->owner();

        foreach (['pending', 'confirmed'] as $status) {
            $r = $this->reservation($user, $status);
            $this->actingAs($user)->deleteJson(self::URL.'/'.$r->id)
                ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
            $this->assertNotNull($r->fresh());
        }

        $cancelled = $this->reservation($user, 'cancelled');
        $this->actingAs($user)->deleteJson(self::URL.'/'.$cancelled->id)->assertNoContent();

        $this->assertNull(DashboardReservation::find($cancelled->id));
        $this->assertNotNull(DashboardReservation::withTrashed()->find($cancelled->id));
    }

    public function test_deleted_reservation_disappears_from_list_but_stays_counted(): void
    {
        $user = $this->owner();
        $r = $this->reservation($user, 'cancelled');

        $this->actingAs($user)->deleteJson(self::URL.'/'.$r->id)->assertNoContent();

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($user)->getJson(self::URL.'/summary')->assertOk()->assertJsonPath('data.used', 1);
    }
}