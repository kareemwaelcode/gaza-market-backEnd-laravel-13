<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnsureStoreOwnerTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'store.owner'])->get('/__owner-probe', fn () => response()->json(['ok' => true]));
    }

    private function owner(string $placeStatus = Place::STATUS_APPROVED, array $userAttrs = []): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create($userAttrs);

        $region = Regions::first() ?? tap(new Regions, function ($r) {
            $r->region_slug = 'r-'.Str::random(6);
            $r->region_name_en = 'Gaza';
            $r->region_name_ar = 'غزة';
            $r->save();
        });
        $area = Area::first() ?? tap(new Area, function ($a) use ($region) {
            $a->area_slug = 'Alrimal';
            $a->area_name_en = 'Al-Rimal';
            $a->area_name_ar = 'الرمال';
            $a->region_id = $region->id;
            $a->save();
        });

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street',
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $area->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $placeStatus;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/__owner-probe')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_approved_active_owner_passes(): void
    {
        $this->actingAs($this->owner())->getJson('/__owner-probe')->assertOk();
    }

    public function test_owner_with_pending_or_rejected_place_is_forbidden(): void
    {
        foreach ([Place::STATUS_PENDING, Place::STATUS_REJECTED] as $status) {
            $this->actingAs($this->owner($status))->getJson('/__owner-probe')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }
    }

    public function test_suspended_owner_is_forbidden(): void
    {
        $this->actingAs($this->owner(userAttrs: ['user_status' => 'suspended']))->getJson('/__owner-probe')->assertStatus(403);
    }

    public function test_plain_user_with_an_approved_place_is_forbidden(): void
    {
        $user = $this->owner();
        $user->user_role = 'user';
        $user->save();

        $this->actingAs($user)->getJson('/__owner-probe')->assertStatus(403);
    }

    public function test_access_is_revoked_immediately_when_the_place_is_rejected(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->getJson('/__owner-probe')->assertOk();

        Place::where('user_id', $user->id)->update(['place_status' => Place::STATUS_REJECTED]);

        $this->actingAs($user->fresh())->getJson('/__owner-probe')->assertStatus(403);
    }
}