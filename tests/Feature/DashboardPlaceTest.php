<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPlaceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/place';

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $this->area = new Area;
        $this->area->area_slug = 'Alrimal';
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    private function owner(string $placeStatus = Place::STATUS_APPROVED): User
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
        $place->place_status = $placeStatus;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->patchJson(self::URL, ['name' => 'X'])->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->postJson(self::URL.'/logo')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_pending_and_rejected_owner_gets_403(): void
    {
        foreach ([Place::STATUS_PENDING, Place::STATUS_REJECTED] as $status) {
            $user = $this->owner($status);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->actingAs($user)->patchJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }
    }

    public function test_plain_user_gets_403(): void
    {
        $user = $this->owner();
        $user->user_role = 'user';
        $user->save();

        $this->actingAs($user)->getJson(self::URL)->assertStatus(403);
    }

    // ---------- القراءة ----------

    public function test_show_returns_only_the_owners_own_place(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $this->actingAs($mine)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.id', $this->placeOf($mine)->id)
            ->assertJsonPath('data.name', $this->placeOf($mine)->place_name)
            ->assertJsonPath('data.type', 'cafe')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.activated', false)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonMissingPath('data.user_id');

        $this->assertNotSame($this->placeOf($other)->id, $this->placeOf($mine)->id);
    }

    // ---------- التعديل ----------

    public function test_patch_updates_allowed_fields(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patchJson(self::URL, [
            'name' => 'اسم جديد',
            'address' => 'عنوان جديد',
            'phone' => '0591112233',
            'whatsapp' => '970591112233',
            'is_open' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'اسم جديد')
            ->assertJsonPath('data.address', 'عنوان جديد')
            ->assertJsonPath('data.phone', '0591112233')
            ->assertJsonPath('data.whatsapp', '970591112233')
            ->assertJsonPath('data.is_open', false);

        $place = $this->placeOf($user);
        $this->assertSame('اسم جديد', $place->place_name);
        $this->assertFalse($place->place_is_open);
    }

    public function test_patch_never_changes_status_plan_type_or_owner(): void
    {
        $user = $this->owner();
        $other = $this->owner();
        $before = $this->placeOf($user)->only(['place_status', 'place_plan', 'place_type', 'user_id', 'place_slug', 'id']);
        $otherBefore = $this->placeOf($other)->toArray();

        $this->actingAs($user)->patchJson(self::URL, [
            'name' => 'Renamed',
            'status' => 'rejected',
            'place_status' => 'rejected',
            'plan' => 'paid',
            'place_plan' => 'paid',
            'type' => 'workspace',
            'place_type' => 'workspace',
            'user_id' => $other->id,
            'id' => $this->placeOf($other)->id,
            'place_id' => $this->placeOf($other)->id,
            'place_slug' => 'hacked',
        ])->assertOk();

        $this->assertSame($before, $this->placeOf($user)->only(['place_status', 'place_plan', 'place_type', 'user_id', 'place_slug', 'id']));
        $this->assertSame('Renamed', $this->placeOf($user)->place_name);
        $this->assertSame($otherBefore, $this->placeOf($other)->toArray());
    }

    public function test_name_or_phone_change_keeps_the_place_approved(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patchJson(self::URL, ['name' => 'Another', 'phone' => '0561234567'])->assertOk();

        $this->assertSame(Place::STATUS_APPROVED, $this->placeOf($user)->place_status);
        $this->actingAs($user->fresh())->getJson(self::URL)->assertOk();
    }

    public function test_patch_with_no_updatable_fields_is_422(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patchJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->patchJson(self::URL, ['status' => 'approved'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
    }

    public function test_patch_validation_errors(): void
    {
        $user = $this->owner();

        foreach ([
            ['name' => ''],
            ['name' => str_repeat('a', 81)],
            ['address' => ''],
            ['phone' => '123'],
            ['phone' => '0521234567'],
            ['whatsapp' => '0591234567'],
            ['whatsapp' => '97059123'],
            ['is_open' => 'maybe'],
        ] as $payload) {
            $this->actingAs($user)->patchJson(self::URL, $payload)
                ->assertStatus(422)
                ->assertJsonPath('code', 'VALIDATION')
                ->assertJsonStructure(['errors' => [array_key_first($payload)]]);
        }
    }

    public function test_phone_taken_by_another_place_is_409(): void
    {
        $user = $this->owner();
        $other = $this->owner();

        $this->actingAs($user)->patchJson(self::URL, ['phone' => $this->placeOf($other)->place_phone])
            ->assertStatus(409)->assertJsonPath('code', 'NUMBER_TAKEN')->assertJsonPath('field', 'phone');
    }

    public function test_whatsapp_taken_by_another_place_is_409(): void
    {
        $user = $this->owner();
        $other = $this->owner();

        $this->actingAs($user)->patchJson(self::URL, ['whatsapp' => $this->placeOf($other)->place_whatsapp])
            ->assertStatus(409)->assertJsonPath('code', 'NUMBER_TAKEN')->assertJsonPath('field', 'whatsapp');
    }

    public function test_sending_own_current_numbers_is_not_a_conflict(): void
    {
        $user = $this->owner();
        $place = $this->placeOf($user);

        $this->actingAs($user)->patchJson(self::URL, [
            'phone' => $place->place_phone,
            'whatsapp' => $place->place_whatsapp,
        ])->assertOk();
    }

    // ---------- الشعار ----------

    public function test_logo_upload_stores_file_and_replaces_the_old_one(): void
    {
        $user = $this->owner();

        $first = $this->actingAs($user)->post(self::URL.'/logo', ['logo' => UploadedFile::fake()->image('a.png', 100, 100)], ['Accept' => 'application/json']);
        $first->assertOk();
        $oldPath = $this->placeOf($user)->place_logo;
        $this->assertNotNull($oldPath);
        $this->assertNotNull($first->json('data.logo_url'));
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($user)->post(self::URL.'/logo', ['logo' => UploadedFile::fake()->image('b.webp', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $newPath = $this->placeOf($user)->place_logo;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertStringStartsWith('places/'.$this->placeOf($user)->id.'/', $newPath);
    }

    public function test_logo_rejects_bad_files(): void
    {
        $user = $this->owner();

        foreach ([
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('shell.php', 1, 'text/x-php'),
            UploadedFile::fake()->image('anim.gif', 100, 100),
            UploadedFile::fake()->image('big.png', 100, 100)->size(5121),
        ] as $file) {
            $this->actingAs($user)->post(self::URL.'/logo', ['logo' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['logo']]);
        }

        $this->actingAs($user)->post(self::URL.'/logo', [], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertNull($this->placeOf($user)->place_logo);
    }

    public function test_logo_upload_does_not_touch_another_owners_place(): void
    {
        $user = $this->owner();
        $other = $this->owner();

        $this->actingAs($user)->post(self::URL.'/logo', ['logo' => UploadedFile::fake()->image('a.jpg', 50, 50), 'place_id' => $this->placeOf($other)->id], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull($this->placeOf($other)->place_logo);
        $this->assertNotNull($this->placeOf($user)->place_logo);
    }

    // ---------- حد الطلبات ----------

    public function test_dashboard_requests_are_rate_limited(): void
    {
        $user = $this->owner();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->getJson(self::URL)->assertOk();
        }

        $this->actingAs($user)->getJson(self::URL)->assertStatus(429);
    }
}