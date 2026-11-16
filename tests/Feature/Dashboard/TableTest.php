<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardTable;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TableTest extends TestCase
{
use RefreshDatabase;

private const URL = '/api/dashboard/tables';

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

private function table(User $owner, string $name = 'طاولة 1', ?int $seats = 4, string $status = 'available'): DashboardTable
{
$table = new DashboardTable(['name' => $name, 'seats' => $seats, 'status' => $status]);
$table->place_id = $this->placeOf($owner)->id;
$table->save();

return $table;
}

// ---------- الوصول ----------

public function test_guest_gets_401_on_all_routes(): void
{
$this->getJson(self::URL)->assertStatus(401);
$this->postJson(self::URL, ['name' => 'X'])->assertStatus(401);
$this->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(401);
$this->deleteJson(self::URL.'/1')->assertStatus(401);
}

public function test_pending_owner_and_plain_user_get_403(): void
{
$this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

$plain = $this->owner();
$plain->user_role = 'user';
$plain->save();
$this->actingAs($plain)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
}

public function test_unsupported_place_types_get_404_on_all_routes(): void
{
foreach (['store', 'restaurant', 'workspace'] as $type) {
    $user = $this->owner($type);

    $this->actingAs($user)->getJson(self::URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(404);
    // 404 يسبق التحقق من المدخلات.
    $this->actingAs($user)->postJson(self::URL, [])->assertStatus(404);
    $this->actingAs($user)->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(404);
    $this->actingAs($user)->deleteJson(self::URL.'/1')->assertStatus(404);
}

$this->assertSame(0, DashboardTable::count());
}

public function test_cafe_and_restaurant_cafe_can_create_tables(): void
{
foreach (['cafe', 'restaurant-cafe'] as $type) {
    $this->actingAs($this->owner($type))->postJson(self::URL, ['name' => 'طاولة 1'])->assertCreated();
}

$this->assertSame(2, DashboardTable::count());
}

// ---------- القائمة ----------

public function test_index_returns_only_own_tables_oldest_first_with_meta(): void
{
$mine = $this->owner();
$other = $this->owner();
$this->table($mine, 'طاولة 1');
$this->table($mine, 'طاولة 2', null, 'occupied');
$this->table($other, 'لغيري');

$response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

$response->assertJsonCount(2, 'data')
    ->assertJsonPath('data.0.name', 'طاولة 1')
    ->assertJsonPath('data.0.seats', 4)
    ->assertJsonPath('data.0.status', 'available')
    ->assertJsonPath('data.1.seats', null)
    ->assertJsonPath('data.1.status', 'occupied')
    ->assertJsonPath('meta.limit', 5)
    ->assertJsonPath('meta.used', 2)
    ->assertJsonPath('meta.hidden', 0)
    ->assertJsonMissingPath('data.0.place_id');

$this->assertIsString($response->json('data.0.id'));
}

public function test_index_meta_limit_is_null_for_paid(): void
{
$this->actingAs($this->owner('cafe', 'paid'))->getJson(self::URL)
    ->assertOk()->assertJsonPath('meta.limit', null);
}

public function test_soft_deleted_tables_are_not_listed_or_counted(): void
{
$user = $this->owner();
$this->table($user, 'باقية');
$this->table($user, 'محذوفة')->delete();

$this->actingAs($user)->getJson(self::URL)
    ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.used', 1);
}

// ---------- الإضافة ----------

public function test_store_creates_a_table_with_all_fields(): void
{
$user = $this->owner();

$this->actingAs($user)->postJson(self::URL, [
    'name' => '  طاولة VIP  ',
    'seats' => 6,
    'status' => 'occupied',
])->assertCreated()
    ->assertJsonPath('data.name', 'طاولة VIP')
    ->assertJsonPath('data.seats', 6)
    ->assertJsonPath('data.status', 'occupied')
    ->assertJsonStructure(['data' => ['id', 'name', 'seats', 'status']]);

$this->assertDatabaseHas('dashboard_tables', [
    'place_id' => $this->placeOf($user)->id,
    'name' => 'طاولة VIP',
    'seats' => 6,
    'status' => 'occupied',
]);
}

public function test_store_defaults_to_available_and_null_seats(): void
{
$this->actingAs($this->owner())->postJson(self::URL, ['name' => 'طاولة 1', 'seats' => ''])
    ->assertCreated()
    ->assertJsonPath('data.status', 'available')
    ->assertJsonPath('data.seats', null);
}

public function test_store_ignores_place_id_sent_by_the_client(): void
{
$mine = $this->owner();
$other = $this->owner();

$this->actingAs($mine)->postJson(self::URL, [
    'name' => 'طاولة 1',
    'place_id' => $this->placeOf($other)->id,
])->assertCreated();

$this->assertSame(1, DashboardTable::where('place_id', $this->placeOf($mine)->id)->count());
$this->assertSame(0, DashboardTable::where('place_id', $this->placeOf($other)->id)->count());
}

public function test_store_allows_duplicate_names(): void
{
$user = $this->owner();

$this->actingAs($user)->postJson(self::URL, ['name' => 'طاولة 1'])->assertCreated();
$this->actingAs($user)->postJson(self::URL, ['name' => 'طاولة 1'])->assertCreated();
}

public function test_store_validates_input(): void
{
$user = $this->owner();

$this->actingAs($user)->postJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');

$bad = [
    ['name' => '   '],
    ['name' => str_repeat('ا', 41)],
    ['name' => 'X', 'seats' => 0],
    ['name' => 'X', 'seats' => -2],
    ['name' => 'X', 'seats' => 51],
    ['name' => 'X', 'seats' => 'abc'],
    ['name' => 'X', 'seats' => 2.5],
    ['name' => 'X', 'status' => 'reserved'],
];

foreach ($bad as $payload) {
    $this->actingAs($user)->postJson(self::URL, $payload)->assertStatus(422);
}

$this->assertSame(0, DashboardTable::count());
}

// ---------- حدود الباقة ----------

public function test_free_limit_is_5_and_paid_is_unlimited(): void
{
$free = $this->owner();

for ($i = 1; $i <= 5; $i++) {
    $this->actingAs($free)->postJson(self::URL, ['name' => "طاولة $i"])->assertCreated();
}

$this->actingAs($free)->postJson(self::URL, ['name' => 'السادسة'])
    ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 5);

$this->assertSame(5, DashboardTable::where('place_id', $this->placeOf($free)->id)->count());

$paid = $this->owner('cafe', 'paid');

for ($i = 1; $i <= 6; $i++) {
    $this->actingAs($paid)->postJson(self::URL, ['name' => "طاولة $i"])->assertCreated();
}
}

public function test_limit_is_per_place(): void
{
$a = $this->owner();
$b = $this->owner();

for ($i = 1; $i <= 5; $i++) {
    $this->table($a, "طاولة $i");
}

$this->actingAs($b)->postJson(self::URL, ['name' => 'X'])->assertCreated();
}

public function test_deleting_a_table_frees_a_slot(): void
{
$user = $this->owner();
$tables = [];

for ($i = 1; $i <= 5; $i++) {
    $tables[] = $this->table($user, "طاولة $i");
}

$this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);

$this->actingAs($user)->deleteJson(self::URL.'/'.$tables[0]->id)->assertNoContent();

$this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertCreated();
}

// ---------- التعديل والحذف ----------

public function test_update_changes_only_sent_fields(): void
{
$user = $this->owner();
$table = $this->table($user, 'طاولة 1', 4);

$this->actingAs($user)->patchJson(self::URL.'/'.$table->id, ['status' => 'occupied'])
    ->assertOk()
    ->assertJsonPath('data.status', 'occupied')
    ->assertJsonPath('data.name', 'طاولة 1')
    ->assertJsonPath('data.seats', 4);

$this->actingAs($user)->patchJson(self::URL.'/'.$table->id, ['name' => ' طاولة 9 ', 'seats' => 8])
    ->assertOk()->assertJsonPath('data.name', 'طاولة 9')->assertJsonPath('data.seats', 8)
    ->assertJsonPath('data.status', 'occupied');

$this->actingAs($user)->patchJson(self::URL.'/'.$table->id, ['seats' => null])
    ->assertOk()->assertJsonPath('data.seats', null);
}

public function test_update_rejects_empty_body_invalid_data_and_foreign_table(): void
{
$mine = $this->owner();
$other = $this->owner();
$own = $this->table($mine);
$foreign = $this->table($other, 'لغيري');

$this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
$this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['name' => ''])->assertStatus(422);
$this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['seats' => 0])->assertStatus(422);
$this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['status' => 'bogus'])->assertStatus(422);
$this->actingAs($mine)->patchJson(self::URL.'/'.$foreign->id, ['name' => 'اختراق'])->assertStatus(404);
$this->actingAs($mine)->patchJson(self::URL.'/999999', ['name' => 'X'])->assertStatus(404);

$this->assertSame('لغيري', $foreign->refresh()->name);
}

public function test_destroy_soft_deletes_own_table_only(): void
{
$mine = $this->owner();
$other = $this->owner();
$own = $this->table($mine);
$foreign = $this->table($other);

$this->actingAs($mine)->deleteJson(self::URL.'/'.$foreign->id)->assertStatus(404);
$this->assertNotSoftDeleted($foreign);

$this->actingAs($mine)->deleteJson(self::URL.'/'.$own->id)->assertNoContent();
$this->assertSoftDeleted($own);
}

public function test_deleted_table_cannot_be_updated_or_deleted_again(): void
{
$user = $this->owner();
$table = $this->table($user);
$table->delete();

$this->actingAs($user)->patchJson(self::URL.'/'.$table->id, ['name' => 'X'])->assertStatus(404);
$this->actingAs($user)->deleteJson(self::URL.'/'.$table->id)->assertStatus(404);
}
}