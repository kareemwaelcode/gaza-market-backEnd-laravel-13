<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardSubscriber;
use App\Models\DashboardSubscriptionRequest;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubscriptionRequestTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_URL = '/api/dashboard/subscription-requests';

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

    private function owner(string $type = 'workspace', string $status = Place::STATUS_APPROVED): User
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
        $place->place_plan = 'free';
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function customer(): User
    {
        return UserFactory::new()->create();
    }

    private function placeOf(User $owner): Place
    {
        return Place::where('user_id', $owner->id)->firstOrFail();
    }

    private function submitUrl(User $owner): string
    {
        return '/api/places/'.$this->placeOf($owner)->place_slug.'/subscription-requests';
    }

    private function futureDate(int $days = 3): string
    {
        return Carbon::now(config('dashboard_plan.subscribers.timezone'))->addDays($days)->toDateString();
    }

    /** @param  array<string, mixed>  $overrides */
    private function body(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'سارة',
            'countryCode' => '970',
            'whatsapp' => '592194533',
            'duration' => 'month',
            'startDate' => $this->futureDate(),
            'note' => 'ملاحظة',
        ];
    }

    private function requestFor(User $owner, User $customer, string $status = DashboardSubscriptionRequest::STATUS_PENDING): DashboardSubscriptionRequest
    {
        $request = new DashboardSubscriptionRequest([
            'name' => 'سارة',
            'country_code' => '970',
            'whatsapp' => '592194533',
            'duration' => 'month',
            'start_date' => $this->futureDate(),
            'note' => null,
        ]);
        $request->place_id = $this->placeOf($owner)->id;
        $request->user_id = $customer->id;
        $request->status = $status;
        $request->save();

        return $request;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->postJson('/api/places/p-abcdefghijkl/subscription-requests', $this->body())->assertStatus(401);
        $this->getJson(self::OWNER_URL)->assertStatus(401);
        $this->postJson(self::OWNER_URL.'/1/accept')->assertStatus(401);
        $this->postJson(self::OWNER_URL.'/1/reject')->assertStatus(401);
        $this->deleteJson(self::OWNER_URL.'/1')->assertStatus(401);
    }

    public function test_plain_user_gets_403_on_owner_routes(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->getJson(self::OWNER_URL)->assertStatus(403);
        $this->actingAs($customer)->postJson(self::OWNER_URL.'/1/accept')->assertStatus(403);
        $this->actingAs($customer)->postJson(self::OWNER_URL.'/1/reject')->assertStatus(403);
        $this->actingAs($customer)->deleteJson(self::OWNER_URL.'/1')->assertStatus(403);
    }

    public function test_unsupported_place_types_get_404_on_owner_routes(): void
    {
        foreach (['cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::OWNER_URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            $this->actingAs($user)->postJson(self::OWNER_URL.'/1/accept')->assertStatus(404);
            $this->actingAs($user)->postJson(self::OWNER_URL.'/1/reject')->assertStatus(404);
            $this->actingAs($user)->deleteJson(self::OWNER_URL.'/1')->assertStatus(404);
        }
    }

    // ---------- إرسال الطلب ----------

    public function test_customer_submits_request(): void
    {
        $owner = $this->owner();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson($this->submitUrl($owner), $this->body())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'status', 'createdAt']]);

        $row = DashboardSubscriptionRequest::firstOrFail();

        $this->assertSame($customer->id, $row->user_id);
        $this->assertSame($this->placeOf($owner)->id, $row->place_id);
        $this->assertSame('سارة', $row->name);
        $this->assertSame('970', $row->country_code);
        $this->assertSame('592194533', $row->whatsapp);
        $this->assertSame('month', $row->duration);
        $this->assertSame('pending', $row->status);
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('invalidBodies')]
    public function test_submit_validates_input(array $override, string $field): void
    {
        $owner = $this->owner();

        $this->actingAs($this->customer())
            ->postJson($this->submitUrl($owner), $this->body($override))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION')
            ->assertJsonStructure(['errors' => [$field]]);

        $this->assertSame(0, DashboardSubscriptionRequest::count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidBodies(): array
    {
        return [
            'bad whatsapp' => [['whatsapp' => '12345'], 'whatsapp'],
            'bad country code' => [['countryCode' => '999'], 'countryCode'],
            'bad duration' => [['duration' => 'year'], 'duration'],
            'missing name' => [['name' => ''], 'name'],
            'past date' => [['startDate' => '2020-01-01'], 'startDate'],
            'far date' => [['startDate' => '2999-01-01'], 'startDate'],
            'long note' => [['note' => str_repeat('x', 301)], 'note'],
        ];
    }

    public function test_submit_to_unknown_pending_or_unsupported_place_gets_404(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson('/api/places/p-abcdefghijkl/subscription-requests', $this->body())
            ->assertStatus(404);

        $pending = $this->owner('workspace', Place::STATUS_PENDING);
        $this->actingAs($customer)->postJson($this->submitUrl($pending), $this->body())->assertStatus(404);

        $cafe = $this->owner('cafe');
        $this->actingAs($customer)->postJson($this->submitUrl($cafe), $this->body())->assertStatus(404);

        $this->assertSame(0, DashboardSubscriptionRequest::count());
    }

    public function test_owner_cannot_submit_to_own_place(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson($this->submitUrl($owner), $this->body())
            ->assertStatus(403)
            ->assertJsonPath('code', 'OWN_PLACE');

        $this->assertSame(0, DashboardSubscriptionRequest::count());
    }

    public function test_second_pending_request_is_rejected_but_allowed_after_rejection(): void
    {
        $owner = $this->owner();
        $customer = $this->customer();

        $this->actingAs($customer)->postJson($this->submitUrl($owner), $this->body())->assertStatus(201);
        $this->actingAs($customer)
            ->postJson($this->submitUrl($owner), $this->body())
            ->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_PENDING');

        $this->assertSame(1, DashboardSubscriptionRequest::count());

        $first = DashboardSubscriptionRequest::firstOrFail();
        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$first->id.'/reject')->assertOk();

        $this->actingAs($customer)->postJson($this->submitUrl($owner), $this->body())->assertStatus(201);
        $this->assertSame(2, DashboardSubscriptionRequest::count());
    }

    public function test_different_customers_can_each_have_a_pending_request(): void
    {
        $owner = $this->owner();

        $this->actingAs($this->customer())->postJson($this->submitUrl($owner), $this->body())->assertStatus(201);
        $this->actingAs($this->customer())->postJson($this->submitUrl($owner), $this->body())->assertStatus(201);

        $this->assertSame(2, DashboardSubscriptionRequest::count());
    }

    // ---------- القائمة ----------

    public function test_owner_lists_only_own_requests_with_filter_and_pending_count(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->requestFor($owner, $this->customer(), DashboardSubscriptionRequest::STATUS_PENDING);
        $this->requestFor($owner, $this->customer(), DashboardSubscriptionRequest::STATUS_ACCEPTED);
        $this->requestFor($owner, $this->customer(), DashboardSubscriptionRequest::STATUS_REJECTED);
        $this->requestFor($other, $this->customer());

        $this->actingAs($owner)
            ->getJson(self::OWNER_URL)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.pending', 1)
            ->assertJsonStructure(['data' => [['id', 'name', 'countryCode', 'whatsapp', 'duration', 'startDate', 'note', 'status', 'createdAt']]]);

        $this->actingAs($owner)
            ->getJson(self::OWNER_URL.'?status=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('meta.pending', 1);

        $this->actingAs($owner)
            ->getJson(self::OWNER_URL.'?status=bogus')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION');
    }

    // ---------- القبول والرفض ----------

    public function test_accept_creates_subscriber_with_zero_amount_and_marks_accepted(): void
    {
        $owner = $this->owner();
        $request = $this->requestFor($owner, $this->customer());

        $response = $this->actingAs($owner)
            ->postJson(self::OWNER_URL.'/'.$request->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $subscriber = DashboardSubscriber::firstOrFail();

        $response->assertJsonPath('subscriberId', (string) $subscriber->id);
        $this->assertSame($this->placeOf($owner)->id, $subscriber->place_id);
        $this->assertSame('سارة', $subscriber->name);
        $this->assertSame('970', $subscriber->country_code);
        $this->assertSame('592194533', $subscriber->whatsapp);
        $this->assertSame('month', $subscriber->duration);
        $this->assertSame(0.0, (float) $subscriber->amount);
        $this->assertSame($this->futureDate(), $subscriber->start_date->toDateString());
        $this->assertFalse($subscriber->is_stopped);
        $this->assertSame('accepted', $request->refresh()->status);
    }

    public function test_second_accept_returns_409_and_does_not_duplicate_subscriber(): void
    {
        $owner = $this->owner();
        $request = $this->requestFor($owner, $this->customer());

        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$request->id.'/accept')->assertOk();
        $this->actingAs($owner)
            ->postJson(self::OWNER_URL.'/'.$request->id.'/accept')
            ->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_HANDLED');

        $this->assertSame(1, DashboardSubscriber::count());
    }

    public function test_reject_marks_rejected_and_blocks_later_accept_or_reject(): void
    {
        $owner = $this->owner();
        $request = $this->requestFor($owner, $this->customer());

        $this->actingAs($owner)
            ->postJson(self::OWNER_URL.'/'.$request->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$request->id.'/accept')->assertStatus(409);
        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$request->id.'/reject')->assertStatus(409);

        $this->assertSame(0, DashboardSubscriber::count());
        $this->assertSame('rejected', $request->refresh()->status);
    }

    public function test_accepted_request_cannot_be_rejected(): void
    {
        $owner = $this->owner();
        $request = $this->requestFor($owner, $this->customer());

        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$request->id.'/accept')->assertOk();
        $this->actingAs($owner)->postJson(self::OWNER_URL.'/'.$request->id.'/reject')->assertStatus(409);

        $this->assertSame('accepted', $request->refresh()->status);
    }

    public function test_owner_cannot_touch_another_places_requests(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();
        $request = $this->requestFor($ownerA, $this->customer());

        $this->actingAs($ownerB)->postJson(self::OWNER_URL.'/'.$request->id.'/accept')->assertStatus(404);
        $this->actingAs($ownerB)->postJson(self::OWNER_URL.'/'.$request->id.'/reject')->assertStatus(404);
        $this->actingAs($ownerB)->deleteJson(self::OWNER_URL.'/'.$request->id)->assertStatus(404);
        $this->actingAs($ownerB)->getJson(self::OWNER_URL)->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame('pending', $request->refresh()->status);
        $this->assertSame(0, DashboardSubscriber::count());
    }

    // ---------- الحذف ----------

    public function test_owner_deletes_request(): void
    {
        $owner = $this->owner();
        $request = $this->requestFor($owner, $this->customer(), DashboardSubscriptionRequest::STATUS_REJECTED);

        $this->actingAs($owner)->deleteJson(self::OWNER_URL.'/'.$request->id)->assertNoContent();

        $this->assertSame(0, DashboardSubscriptionRequest::count());
    }
}