<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaceTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    private Area $area;

    private StoreCategory $category;

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

        $group = new StoreCategoryGroup;
        $group->forceFill([
            'store_category_group_slug' => 'g-'.Str::random(6),
            'store_category_group_name_en' => 'Food',
            'store_category_group_name_ar' => 'طعام',
        ])->save();

        $this->category = new StoreCategory;
        $this->category->forceFill([
            'store_category_slug' => 'supermarket',
            'store_category_variant_key' => 'supermarket',
            'store_category_name_en' => 'Supermarket',
            'store_category_name_ar' => 'سوبرماركت',
            'store_category_is_active' => true,
            'store_category_group_id' => $group->id,
        ])->save();
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->user_phone = '059'.str_pad((string) ++self::$counter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Owner '.self::$counter;
        $user->save();

        return $user;
    }

    private function payload(array $override = []): array
    {
        $n = ++self::$counter;

        return array_merge([
            'type' => 'cafe',
            'name' => 'Cafe '.$n,
            'region' => 'Alrimal',
            'address' => 'Street '.$n,
            'phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'whatsapp' => '970592'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
        ], $override);
    }

    private function makePlace(?User $owner = null, string $status = Place::STATUS_PENDING): Place
    {
        $n = ++self::$counter;
        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street',
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->user_id = ($owner ?? $this->makeUser())->id;
        $place->save();

        return $place;
    }

    public function test_guest_cannot_register_or_read_mine(): void
    {
        $this->postJson('/api/places', $this->payload())->assertUnauthorized();
        $this->getJson('/api/places/mine')->assertUnauthorized();
    }

    public function test_user_registers_a_place_as_pending(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->postJson('/api/places', $this->payload([
            'image' => UploadedFile::fake()->image('shop.jpg'),
        ]));

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $place = Place::firstOrFail();
        $this->assertSame($user->id, $place->user_id);
        $this->assertSame($this->area->id, $place->area_id);
        $this->assertSame('pending', $place->place_status);
        $this->assertSame('free', $place->place_plan);
        $this->assertMatchesRegularExpression('/^p-[a-z0-9]{12}$/', $place->place_slug);
        Storage::disk('public')->assertExists($place->place_logo);
    }

    public function test_image_is_optional(): void
    {
        $this->actingAs($this->makeUser())->postJson('/api/places', $this->payload())->assertCreated();

        $this->assertNull(Place::firstOrFail()->place_logo);
    }

    public function test_store_type_requires_a_known_sub_category(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/places', $this->payload(['type' => 'store']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION')
            ->assertJsonStructure(['errors' => ['sub_category']]);

        $this->actingAs($user)->postJson('/api/places', $this->payload(['type' => 'store', 'sub_category' => 'Unknown']))
            ->assertStatus(422);

        $this->actingAs($user)->postJson('/api/places', $this->payload(['type' => 'store', 'sub_category' => 'Supermarket']))
            ->assertCreated();

        $this->assertSame($this->category->id, Place::firstOrFail()->store_category_id);
    }

    public function test_sub_category_is_ignored_for_non_store_types(): void
    {
        $this->actingAs($this->makeUser())
            ->postJson('/api/places', $this->payload(['type' => 'cafe', 'sub_category' => 'Supermarket']))
            ->assertCreated();

        $this->assertNull(Place::firstOrFail()->store_category_id);
    }

    public function test_validation_errors_use_frontend_field_names(): void
    {
        $this->actingAs($this->makeUser())->postJson('/api/places', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION')
            ->assertJsonStructure(['errors' => ['type', 'name', 'region', 'address', 'phone', 'whatsapp']]);
    }

    public function test_phone_whatsapp_region_and_image_are_validated(): void
    {
        $user = $this->makeUser();

        foreach ([
            ['phone' => '0591234', 'field' => 'phone'],
            ['phone' => '970591234567', 'field' => 'phone'],
            ['whatsapp' => '0591234567', 'field' => 'whatsapp'],
            ['whatsapp' => '+970591234567', 'field' => 'whatsapp'],
            ['region' => 'Nowhere', 'field' => 'region'],
            ['type' => 'bank', 'field' => 'type'],
            ['name' => str_repeat('a', 81), 'field' => 'name'],
            ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'field' => 'image'],
            ['image' => UploadedFile::fake()->image('big.jpg')->size(6000), 'field' => 'image'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);

            $this->actingAs($user)->postJson('/api/places', $this->payload($case))
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => [$field]]);
        }

        $this->assertSame(0, Place::count());
    }

    public function test_second_place_for_same_user_is_refused(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/places', $this->payload())->assertCreated();

        $this->actingAs($user)->postJson('/api/places', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_HAS_PLACE');

        $this->assertSame(1, Place::count());
    }

    public function test_taken_phone_and_whatsapp_are_reported_with_field(): void
    {
        $other = $this->makePlace();

        $this->actingAs($this->makeUser())
            ->postJson('/api/places', $this->payload(['phone' => $other->place_phone]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'NUMBER_TAKEN')
            ->assertJsonPath('field', 'phone');

        $this->actingAs($this->makeUser())
            ->postJson('/api/places', $this->payload(['whatsapp' => $other->place_whatsapp]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'NUMBER_TAKEN')
            ->assertJsonPath('field', 'whatsapp');
    }

    public function test_rejected_place_can_be_edited_and_resubmitted_as_pending(): void
    {
        $user = $this->makeUser();
        $place = $this->makePlace($user, Place::STATUS_REJECTED);

        $payload = $this->payload([
            'name' => 'Renamed',
            'phone' => $place->place_phone,
            'whatsapp' => $place->place_whatsapp,
        ]);

        $this->actingAs($user)->postJson('/api/places', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $place->id)
            ->assertJsonPath('data.status', 'pending');

        $place->refresh();
        $this->assertSame('Renamed', $place->place_name);
        $this->assertSame('pending', $place->place_status);
        $this->assertSame(1, Place::count());
    }

    public function test_pending_or_approved_place_cannot_be_resubmitted(): void
    {
        foreach ([Place::STATUS_PENDING, Place::STATUS_APPROVED] as $status) {
            $user = $this->makeUser();
            $this->makePlace($user, $status);

            $this->actingAs($user)->postJson('/api/places', $this->payload())
                ->assertStatus(409)
                ->assertJsonPath('code', 'ALREADY_HAS_PLACE');
        }
    }

    public function test_resubmit_replaces_the_old_logo(): void
    {
        $user = $this->makeUser();
        $place = $this->makePlace($user, Place::STATUS_REJECTED);
        Storage::disk('public')->put("places/{$place->id}/old.jpg", 'x');
        $place->place_logo = "places/{$place->id}/old.jpg";
        $place->save();

        $this->actingAs($user)->postJson('/api/places', $this->payload([
            'phone' => $place->place_phone,
            'whatsapp' => $place->place_whatsapp,
            'image' => UploadedFile::fake()->image('new.jpg'),
        ]))->assertOk();

        $place->refresh();
        Storage::disk('public')->assertMissing("places/{$place->id}/old.jpg");
        Storage::disk('public')->assertExists($place->place_logo);
    }

    public function test_mine_returns_null_without_a_place(): void
    {
        $this->actingAs($this->makeUser())->getJson('/api/places/mine')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_mine_returns_only_own_place_without_private_fields(): void
    {
        $user = $this->makeUser();
        $mine = $this->makePlace($user, Place::STATUS_APPROVED);
        $this->makePlace();

        $response = $this->actingAs($user)->getJson('/api/places/mine')
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.activated', false);

        $response->assertJsonMissingPath('data.user_id');
        $response->assertJsonMissingPath('data.phone');
    }

    public function test_mine_exposes_sub_category_name_and_activation(): void
    {
        $user = $this->makeUser();
        $place = $this->makePlace($user, Place::STATUS_APPROVED);
        $place->place_type = 'store';
        $place->store_category_id = $this->category->id;
        $place->place_activated_at = now();
        $place->save();

        $this->actingAs($user)->getJson('/api/places/mine')
            ->assertJsonPath('data.sub_category', 'Supermarket')
            ->assertJsonPath('data.activated', true);
    }

    public function test_database_enforces_one_place_per_user(): void
    {
        $user = $this->makeUser();
        $this->makePlace($user);

        $this->expectException(QueryException::class);
        $this->makePlace($user);
    }

    public function test_registration_is_rate_limited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/places', [])->assertStatus(422);
        }

        $this->actingAs($user)->postJson('/api/places', [])->assertStatus(429);
    }

    public function test_cms_index_lists_pending_first_and_filters(): void
    {
        $approved = $this->makePlace(null, Place::STATUS_APPROVED);
        $pending = $this->makePlace(null, Place::STATUS_PENDING);

        $this->get('/cms/admins/place')
            ->assertOk()
            ->assertSeeInOrder([$pending->place_name, $approved->place_name]);

        $this->get('/cms/admins/place?status=approved')
            ->assertOk()
            ->assertSee($approved->place_name)
            ->assertDontSee($pending->place_name);

        $this->get('/cms/admins/place?status=bogus')->assertOk()->assertSee('Invalid filter value ignored');
        $this->getJson('/cms/admins/place?status=bogus')->assertStatus(422);
    }

    public function test_cms_show_displays_the_place(): void
    {
        $place = $this->makePlace();

        $this->get("/cms/admins/place/{$place->id}")->assertOk()->assertSee($place->place_name);
        $this->get('/cms/admins/place/99999')->assertNotFound();
    }

    public function test_cms_approve_and_reject_follow_allowed_transitions(): void
    {
        $place = $this->makePlace();

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();
        $place->refresh();
        $this->assertSame('approved', $place->place_status);
        $this->assertNotNull($place->place_approved_at);

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertStatus(409);

        $this->postJson("/cms/admins/place_reject/{$place->id}")->assertOk();
        $place->refresh();
        $this->assertSame('rejected', $place->place_status);
        $this->assertNull($place->place_approved_at);

        $this->postJson("/cms/admins/place_reject/{$place->id}")->assertStatus(409);

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();
        $this->postJson('/cms/admins/place_approve/99999')->assertNotFound();
    }

    public function test_cms_destroy_removes_place_and_files(): void
    {
        $place = $this->makePlace();
        Storage::disk('public')->put("places/{$place->id}/logo.jpg", 'x');

        $this->deleteJson("/cms/admins/place/{$place->id}")->assertOk();

        $this->assertNull(Place::find($place->id));
        Storage::disk('public')->assertMissing("places/{$place->id}/logo.jpg");
    }
}