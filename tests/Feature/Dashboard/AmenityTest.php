<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardAmenity;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmenityTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/amenities';

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

    private function owner(string $type = 'workspace', string $plan = 'free', string $status = Place::STATUS_APPROVED): User
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

    /** @param  array<string, mixed>  $services */
    private function body(array $services): array
    {
        return ['services' => $services];
    }

    private function sample(): array
    {
        return [
            'wifi' => ['enabled' => true, 'details' => 'انترنت فائق السرعة'],
            'electricity' => ['enabled' => true, 'details' => ''],
            'printing' => ['enabled' => false, 'details' => 'طباعة'],
        ];
    }

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->putJson(self::URL, $this->body([]))->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->putJson(self::URL, $this->body($this->sample()))->assertStatus(403);
    }

    public function test_every_place_type_has_access(): void
    {
        foreach (['workspace', 'cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::URL)->assertOk();
            $this->actingAs($user)->putJson(self::URL, $this->body(['wifi' => ['enabled' => true]]))->assertOk();
        }

        $this->assertSame(5, DashboardAmenity::count());
    }

    public function test_show_returns_empty_object_when_nothing_saved(): void
    {
        $response = $this->actingAs($this->owner())->getJson(self::URL)->assertOk();

        $this->assertSame('{}', json_encode($response->json('data.services') ?: new \stdClass));
        $this->assertStringContainsString('"services":{}', $response->getContent());
    }

    public function test_update_stores_only_enabled_services_with_expected_shape(): void
    {
        $user = $this->owner();

        $response = $this->actingAs($user)->putJson(self::URL, $this->body($this->sample()))->assertOk();

        $this->assertSame([
            'wifi' => ['enabled' => true, 'details' => 'انترنت فائق السرعة'],
            'electricity' => ['enabled' => true, 'details' => ''],
        ], $response->json('data.services'));

        $this->assertSame(2, DashboardAmenity::count());
        $this->assertDatabaseHas('dashboard_amenities', [
            'place_id' => $this->placeOf($user)->id,
            'service_key' => 'wifi',
            'details' => 'انترنت فائق السرعة',
        ]);
        $this->assertDatabaseHas('dashboard_amenities', [
            'place_id' => $this->placeOf($user)->id,
            'service_key' => 'electricity',
            'details' => null,
        ]);
        $this->assertDatabaseMissing('dashboard_amenities', ['service_key' => 'printing']);

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.services.wifi.enabled', true)
            ->assertJsonPath('data.services.electricity.details', '');
    }

    public function test_update_replaces_the_whole_set(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, $this->body($this->sample()))->assertOk();

        $response = $this->actingAs($user)->putJson(self::URL, $this->body([
            'wifi' => ['enabled' => true, 'details' => 'جديد'],
            'electricity' => ['enabled' => false],
            'drinks' => ['enabled' => true],
        ]))->assertOk();

        $this->assertSame(['wifi', 'drinks'], array_keys($response->json('data.services')));
        $this->assertSame('جديد', $response->json('data.services.wifi.details'));
        $this->assertSame(2, DashboardAmenity::count());
        $this->assertDatabaseMissing('dashboard_amenities', ['service_key' => 'electricity']);
    }

    public function test_empty_services_clears_everything(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, $this->body($this->sample()))->assertOk();
        $response = $this->actingAs($user)->putJson(self::URL, $this->body([]))->assertOk();

        $this->assertStringContainsString('"services":{}', $response->getContent());
        $this->assertSame(0, DashboardAmenity::count());
    }

    public function test_details_are_trimmed_and_blank_becomes_null(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, $this->body([
            'wifi' => ['enabled' => true, 'details' => '  سريع  '],
            'drinks' => ['enabled' => true, 'details' => '   '],
            'parking' => ['enabled' => true, 'details' => null],
        ]))->assertOk();

        $this->assertDatabaseHas('dashboard_amenities', ['service_key' => 'wifi', 'details' => 'سريع']);
        $this->assertDatabaseHas('dashboard_amenities', ['service_key' => 'drinks', 'details' => null]);
        $this->assertDatabaseHas('dashboard_amenities', ['service_key' => 'parking', 'details' => null]);
    }

    public function test_details_longer_than_120_are_rejected(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->putJson(self::URL, $this->body(['wifi' => ['enabled' => true, 'details' => str_repeat('ا', 121)]]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION');

        $this->actingAs($user)
            ->putJson(self::URL, $this->body(['wifi' => ['enabled' => true, 'details' => str_repeat('ا', 120)]]))
            ->assertOk();
    }

    public function test_invalid_service_keys_are_rejected(): void
    {
        $user = $this->owner();

        foreach (['Wifi', '1wifi', 'wi.fi', 'wi fi', '_wifi', str_repeat('a', 33), ''] as $key) {
            $this->actingAs($user)
                ->putJson(self::URL, $this->body([$key => ['enabled' => true]]))
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->putJson(self::URL, $this->body([['enabled' => true]]))
            ->assertStatus(422);

        $this->assertSame(0, DashboardAmenity::count());
    }

    public function test_hyphen_and_underscore_keys_are_accepted(): void
    {
        $this->actingAs($this->owner('restaurant', 'paid'))
            ->putJson(self::URL, $this->body([
                'payment_methods-tow' => ['enabled' => true],
                'family_section' => ['enabled' => true],
                str_repeat('a', 32) => ['enabled' => true],
            ]))
            ->assertOk();

        $this->assertSame(3, DashboardAmenity::count());
    }

    public function test_services_count_is_capped_at_thirty(): void
    {
        $user = $this->owner();

        $make = function (int $count): array {
            $services = [];

            for ($i = 0; $i < $count; $i++) {
                $services['k'.chr(97 + intdiv($i, 26)).chr(97 + $i % 26)] = ['enabled' => true];
            }

            return $services;
        };

        $this->actingAs($user)->putJson(self::URL, $this->body($make(31)))->assertStatus(422);
        $this->assertSame(0, DashboardAmenity::count());

        $this->actingAs($user)->putJson(self::URL, $this->body($make(30)))->assertOk();
        $this->assertSame(30, DashboardAmenity::count());
    }

    public function test_malformed_bodies_are_rejected(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->putJson(self::URL, ['services' => 'wifi'])->assertStatus(422);
        $this->actingAs($user)->putJson(self::URL, $this->body(['wifi' => true]))->assertStatus(422);
        $this->actingAs($user)->putJson(self::URL, $this->body(['wifi' => []]))->assertStatus(422);
        $this->actingAs($user)->putJson(self::URL, $this->body(['wifi' => ['enabled' => 'yes']]))->assertStatus(422);
        $this->actingAs($user)->putJson(self::URL, $this->body(['wifi' => ['enabled' => true, 'details' => ['x']]]))->assertStatus(422);

        $this->assertSame(0, DashboardAmenity::count());
    }

    public function test_free_plan_cannot_enable_locked_services(): void
    {
        $user = $this->owner('store', 'free');

        $this->actingAs($user)->putJson(self::URL, $this->body(['delivery' => ['enabled' => true]]))->assertOk();

        $response = $this->actingAs($user)->putJson(self::URL, $this->body([
            'wifi' => ['enabled' => true],
            'card_payment' => ['enabled' => true],
            'payment_methods-tow' => ['enabled' => true],
            'whatsapp_order' => ['enabled' => true],
            'installments' => ['enabled' => true],
        ]))->assertStatus(403)->assertJsonPath('code', 'FEATURE_LOCKED');

        $this->assertEqualsCanonicalizing(
            ['card_payment', 'payment_methods-tow', 'whatsapp_order', 'installments'],
            $response->json('services')
        );

        $this->assertSame(1, DashboardAmenity::count());
        $this->assertDatabaseHas('dashboard_amenities', ['service_key' => 'delivery']);
    }

    public function test_free_plan_can_send_locked_services_when_disabled(): void
    {
        $this->actingAs($this->owner('store', 'free'))
            ->putJson(self::URL, $this->body([
                'card_payment' => ['enabled' => false],
                'installments' => ['enabled' => false],
                'delivery' => ['enabled' => true],
            ]))
            ->assertOk();

        $this->assertSame(1, DashboardAmenity::count());
    }

    public function test_paid_plan_can_enable_locked_services(): void
    {
        $this->actingAs($this->owner('store', 'paid'))
            ->putJson(self::URL, $this->body([
                'card_payment' => ['enabled' => true],
                'whatsapp_order' => ['enabled' => true],
                'installments' => ['enabled' => true],
            ]))
            ->assertOk();

        $this->assertSame(3, DashboardAmenity::count());
    }

    public function test_owners_cannot_see_or_change_each_others_services(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        $this->actingAs($a)->putJson(self::URL, $this->body(['wifi' => ['enabled' => true, 'details' => 'A']]))->assertOk();
        $this->actingAs($b)->putJson(self::URL, $this->body(['drinks' => ['enabled' => true, 'details' => 'B']]))->assertOk();

        $this->assertSame(['wifi'], array_keys($this->actingAs($a)->getJson(self::URL)->json('data.services')));
        $this->assertSame(['drinks'], array_keys($this->actingAs($b)->getJson(self::URL)->json('data.services')));

        $this->actingAs($b)->putJson(self::URL, $this->body([]))->assertOk();

        $this->assertDatabaseHas('dashboard_amenities', ['place_id' => $this->placeOf($a)->id, 'service_key' => 'wifi']);
        $this->assertSame(1, DashboardAmenity::count());
    }

    public function test_place_id_and_extra_fields_in_body_are_ignored(): void
    {
        $user = $this->owner();
        $other = $this->owner();

        $this->actingAs($user)->putJson(self::URL, [
            'place_id' => $this->placeOf($other)->id,
            'services' => ['wifi' => ['enabled' => true, 'place_id' => $this->placeOf($other)->id, 'extra' => 'x']],
        ])->assertOk();

        $this->assertDatabaseHas('dashboard_amenities', ['place_id' => $this->placeOf($user)->id, 'service_key' => 'wifi']);
        $this->assertDatabaseMissing('dashboard_amenities', ['place_id' => $this->placeOf($other)->id]);
    }

    public function test_database_allows_one_row_per_place_and_service(): void
    {
        $placeId = $this->placeOf($this->owner())->id;

        $first = new DashboardAmenity(['service_key' => 'wifi']);
        $first->place_id = $placeId;
        $first->save();

        $this->expectException(UniqueConstraintViolationException::class);

        $second = new DashboardAmenity(['service_key' => 'wifi']);
        $second->place_id = $placeId;
        $second->save();
    }
}