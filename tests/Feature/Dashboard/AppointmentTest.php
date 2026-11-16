<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardAppointment;
use App\Models\DashboardClinicHour;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Tests\Feature\Dashboard\Concerns\MakesClinicPlaces;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use MakesClinicPlaces;
    use RefreshDatabase;

    private const DASH = '/api/dashboard/appointments';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPlaces();
        $this->freezeNow();

        // الـ throttle له ملفه (AppointmentThrottleTest)؛ هنا نكرر الطلبات من IP واحد.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** رقم واتساب صالح (9 خانات تبدأ بـ 5) مختلف لكل $n. */
    private function phone(int $n): string
    {
        return '59'.str_pad((string) $n, 7, '0', STR_PAD_LEFT);
    }

    private function url(User $owner): string
    {
        return '/api/places/'.$this->placeOf($owner)->place_slug.'/appointments';
    }

    private function book(User $owner, array $override = [])
    {
        return $this->postJson($this->url($owner), $this->payload($override));
    }

    private function appointment(User $owner, array $attrs = []): DashboardAppointment
    {
        $a = new DashboardAppointment(array_merge([
            'name' => 'مراجع',
            'country_code' => '970',
            'whatsapp' => '599000000',
            'scheduled_at' => Carbon::parse($this->at('10:00'))->utc(),
        ], $attrs));
        $a->place_id = $this->placeOf($owner)->id;
        $a->status = $attrs['status'] ?? DashboardAppointment::STATUS_PENDING;
        $a->save();

        return $a;
    }

    // ---------- الحجز العام ----------

    public function test_guest_books_a_pending_appointment_and_gets_only_id_and_status(): void
    {
        $owner = $this->makeOwner();

        $response = $this->book($owner)->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'status']])
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(['id', 'status'], array_keys($response->json('data')));
        $this->assertStringNotContainsString('أحمد', $response->getContent());
        $this->assertStringNotContainsString('599123456', $response->getContent());

        $row = DashboardAppointment::firstOrFail();
        $this->assertSame($this->placeOf($owner)->id, $row->place_id);
        $this->assertSame('pending', $row->status);
        $this->assertSame('أحمد علي', $row->name);
        $this->assertSame('970', $row->country_code);
        $this->assertSame('فحص عام', $row->reason);
        $this->assertNull($row->note);
        // يُخزَّن UTC: 10:00 بتوقيت غزة = 08:00 UTC.
        $this->assertSame('2026-11-18 08:00:00', $row->scheduled_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_unknown_pending_and_non_clinic_places_return_404(): void
    {
        $this->postJson('/api/places/p-aaaaaaaaaaaa/appointments', $this->payload())->assertStatus(404);

        $notClinic = [
            $this->makeOwner('pharmacy'),
            $this->makeOwner(null),
            $this->makeOwner(null, type: 'cafe'),
            $this->makeOwner('clinic', status: Place::STATUS_PENDING),
            $this->makeOwner('clinic', status: Place::STATUS_REJECTED),
        ];

        foreach ($notClinic as $owner) {
            $this->book($owner)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->assertSame(0, DashboardAppointment::count());
    }

    public function test_it_ignores_status_and_place_id_sent_by_the_client(): void
    {
        $owner = $this->makeOwner();
        $other = $this->makeOwner();

        $this->book($owner, ['status' => 'confirmed', 'place_id' => $this->placeOf($other)->id])->assertCreated();

        $row = DashboardAppointment::firstOrFail();
        $this->assertSame('pending', $row->status);
        $this->assertSame($this->placeOf($owner)->id, $row->place_id);
    }

    public function test_it_validates_input(): void
    {
        $owner = $this->makeOwner();

        $bad = [
            [],
            ['name' => 'أ'],
            ['name' => str_repeat('ا', 81)],
            ['name' => '   '],
            ['countryCode' => '1'],
            ['whatsapp' => '49123456'],
            ['whatsapp' => '5991234567'],
            ['whatsapp' => '59912345'],
            ['datetime' => 'غدا'],
            ['datetime' => '2026-11-18'],
            ['datetime' => '2026-13-45T10:00:00Z'],
            ['datetime' => '2026-12-31T25:00:00Z'],
            ['datetime' => '2026-02-31T10:00:00Z'],
            ['datetime' => $this->at('10:00', -1)],     // بالماضي
            ['datetime' => $this->at('10:00', 31)],     // أبعد من 30 يوماً
            ['reason' => str_repeat('ا', 101)],
            ['note' => str_repeat('ا', 301)],
        ];

        foreach ($bad as $override) {
            $payload = $override === [] ? [] : $this->payload($override);
            $this->postJson($this->url($owner), $payload)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }

        $this->assertSame(0, DashboardAppointment::count());
    }

    public function test_past_and_far_errors_use_the_frontend_keys(): void
    {
        $owner = $this->makeOwner();

        $this->book($owner, ['datetime' => $this->at('10:00', -1)])
            ->assertStatus(422)->assertJsonPath('errors.datetime.0', 'book.errPast');
        $this->book($owner, ['datetime' => $this->at('10:00', 31)])
            ->assertStatus(422)->assertJsonPath('errors.datetime.0', 'book.errFar');
    }

    public function test_a_time_earlier_today_is_in_the_past(): void
    {
        $owner = $this->makeOwner();

        // الآن 10:00 بغزة؛ 09:30 اليوم ماضٍ.
        $this->book($owner, ['datetime' => $this->at('09:30', 0)])
            ->assertStatus(422)->assertJsonPath('errors.datetime.0', 'book.errPast');
        $this->book($owner, ['datetime' => $this->at('10:30', 0)])->assertCreated();
    }

    public function test_it_respects_default_working_hours_and_slot_grid(): void
    {
        $owner = $this->makeOwner();

        // الدوام الافتراضي 09:00-17:00، مدة 30 دقيقة، الجمعة مغلقة.
        $ok = ['09:00', '09:30', '16:30'];
        foreach ($ok as $time) {
            $this->book($owner, ['datetime' => $this->at($time), 'whatsapp' => '59'.random_int(1000000, 9999999)])->assertCreated();
        }

        $rejected = [
            ['08:30', 2],   // قبل الفتح
            ['17:00', 2],   // وقت الإغلاق نفسه
            ['16:45', 2],   // يتجاوز الإغلاق ولا يطابق الشبكة
            ['10:10', 2],   // خارج شبكة 30 دقيقة
            ['10:00', 4],   // الجمعة مغلقة
        ];
        foreach ($rejected as [$time, $days]) {
            $this->book($owner, ['datetime' => $this->at($time, $days), 'whatsapp' => '59'.random_int(1000000, 9999999)])
                ->assertStatus(422)->assertJsonPath('errors.datetime.0', 'book.errTime');
        }

        $this->assertSame(3, DashboardAppointment::count());
    }

    public function test_it_uses_the_hours_saved_by_the_owner(): void
    {
        $owner = $this->makeOwner('clinic', 'paid');

        // الأربعاء فقط 13:00-15:00 بمدة 60 دقيقة.
        $days = array_map(
            fn (string $key) => ['key' => $key, 'open' => $key === 'wed', 'from' => '13:00', 'to' => '15:00'],
            DashboardClinicHour::DAYS
        );
        $this->actingAs($owner)->putJson('/api/dashboard/clinic-hours', ['slot' => 60, 'days' => $days])->assertOk();

        $this->book($owner, ['datetime' => $this->at('13:00'), 'whatsapp' => '597000001'])->assertCreated();
        $this->book($owner, ['datetime' => $this->at('14:00'), 'whatsapp' => '597000002'])->assertCreated();

        $rejected = [
            ['10:00', 2],   // قبل الفتح
            ['13:30', 2],   // خارج شبكة 60 دقيقة
            ['15:00', 2],   // عند الإغلاق
            ['13:00', 3],   // الخميس مغلق
        ];
        foreach ($rejected as [$time, $daysAhead]) {
            $this->book($owner, ['datetime' => $this->at($time, $daysAhead), 'whatsapp' => '597000003'])
                ->assertStatus(422)->assertJsonPath('errors.datetime.0', 'book.errTime');
        }
    }

    public function test_free_plan_limit_blocks_the_sixth_appointment_and_deleted_ones_still_count(): void
    {
        $owner = $this->makeOwner();
        $times = ['09:00', '10:00', '11:00', '12:00', '13:00'];

        foreach ($times as $i => $time) {
            $this->book($owner, ['datetime' => $this->at($time), 'whatsapp' => $this->phone($i)])->assertCreated();
        }

        $this->book($owner, ['datetime' => $this->at('14:00'), 'whatsapp' => $this->phone(98)])
            ->assertStatus(403)->assertJsonPath('code', 'plan_limit_reached');

        // الحذف الناعم لا يُرجع مكاناً.
        $first = DashboardAppointment::orderBy('id')->first();
        $first->status = DashboardAppointment::STATUS_CANCELLED;
        $first->save();
        $first->delete();

        $this->book($owner, ['datetime' => $this->at('14:00'), 'whatsapp' => $this->phone(99)])->assertStatus(403);
        $this->assertSame(5, DashboardAppointment::withTrashed()->count());
    }

    public function test_paid_plan_has_no_commercial_limit(): void
    {
        $owner = $this->makeOwner('clinic', 'paid');

        foreach (['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'] as $i => $time) {
            $this->book($owner, ['datetime' => $this->at($time), 'whatsapp' => $this->phone($i)])->assertCreated();
        }

        $this->assertSame(7, DashboardAppointment::count());
    }

    public function test_limit_is_per_clinic(): void
    {
        $a = $this->makeOwner();
        $b = $this->makeOwner();

        foreach (['09:00', '10:00', '11:00', '12:00', '13:00'] as $i => $time) {
            $this->book($a, ['datetime' => $this->at($time), 'whatsapp' => $this->phone($i)])->assertCreated();
        }

        $this->book($b, ['whatsapp' => '593999999'])->assertCreated();
    }

    public function test_a_confirmed_appointment_blocks_the_same_time_but_pending_and_cancelled_do_not(): void
    {
        $owner = $this->makeOwner('clinic', 'paid');
        $confirmed = $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CONFIRMED]);

        $this->book($owner, ['whatsapp' => '594000001'])
            ->assertStatus(409)->assertJsonPath('code', 'slot_unavailable');

        // وقت متداخل (10:15 لا يطابق الشبكة أصلاً)، فنتحقق من المجاور على الشبكة.
        $this->book($owner, ['datetime' => $this->at('10:30'), 'whatsapp' => '594000002'])->assertCreated();
        $this->book($owner, ['datetime' => $this->at('09:30'), 'whatsapp' => '594000003'])->assertCreated();

        // المعلّق لا يحجز: موعدان معلّقان بنفس الوقت مقبولان.
        $this->book($owner, ['datetime' => $this->at('12:00'), 'whatsapp' => '594000004'])->assertCreated();
        $this->book($owner, ['datetime' => $this->at('12:00'), 'whatsapp' => '594000005'])->assertCreated();

        // الملغى لا يحجز.
        $confirmed->status = DashboardAppointment::STATUS_CANCELLED;
        $confirmed->save();
        $this->book($owner, ['whatsapp' => '594000006'])->assertCreated();
    }

    public function test_a_confirmed_appointment_blocks_overlap_when_slot_is_longer_than_the_grid_step(): void
    {
        $owner = $this->makeOwner('clinic', 'paid');
        $placeId = $this->placeOf($owner)->id;

        // مدة 60 دقيقة والدوام 09:00-17:00 (الشبكة كل 60): 10:00 مؤكد يمنع 10:00 فقط ويسمح بـ 09:00 و11:00.
        foreach (DashboardClinicHour::DAYS as $key) {
            $h = new DashboardClinicHour(['day_of_week' => $key, 'is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'slot_minutes' => 60]);
            $h->place_id = $placeId;
            $h->save();
        }

        $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CONFIRMED]);

        $this->book($owner, ['whatsapp' => '595000001'])->assertStatus(409);
        $this->book($owner, ['datetime' => $this->at('09:00'), 'whatsapp' => '595000002'])->assertCreated();
        $this->book($owner, ['datetime' => $this->at('11:00'), 'whatsapp' => '595000003'])->assertCreated();
        // 09:30 خارج شبكة الـ 60 دقيقة.
        $this->book($owner, ['datetime' => $this->at('09:30'), 'whatsapp' => '595000004'])->assertStatus(422);
    }

    public function test_the_route_is_public_but_needs_no_login(): void
    {
        $owner = $this->makeOwner();

        $this->assertGuest();
        $this->book($owner)->assertCreated();
    }

    // ---------- لوحة المالك: الوصول ----------

    public function test_dashboard_requires_login_and_an_approved_owner(): void
    {
        $this->getJson(self::DASH)->assertStatus(401);
        $this->getJson(self::DASH.'/summary')->assertStatus(401);
        $this->patchJson(self::DASH.'/1', ['status' => 'confirmed'])->assertStatus(401);
        $this->deleteJson(self::DASH.'/1')->assertStatus(401);

        $this->actingAs($this->makeOwner(status: Place::STATUS_PENDING))->getJson(self::DASH)->assertStatus(403);
    }

    public function test_non_clinic_owners_get_404_on_every_dashboard_route(): void
    {
        foreach ([$this->makeOwner('pharmacy'), $this->makeOwner(null), $this->makeOwner(null, type: 'cafe')] as $owner) {
            $this->actingAs($owner)->getJson(self::DASH)->assertStatus(404);
            $this->actingAs($owner)->getJson(self::DASH.'/summary')->assertStatus(404);
            $this->actingAs($owner)->patchJson(self::DASH.'/1', ['status' => 'confirmed'])->assertStatus(404);
            $this->actingAs($owner)->deleteJson(self::DASH.'/1')->assertStatus(404);
        }
    }

    // ---------- القائمة والملخص ----------

    public function test_index_returns_only_own_appointments_newest_first_with_frontend_shape(): void
    {
        $mine = $this->makeOwner();
        $other = $this->makeOwner();
        $old = $this->appointment($mine, ['name' => 'قديم']);
        $new = $this->appointment($mine, ['name' => 'جديد', 'reason' => 'تطعيم', 'note' => 'ملاحظة']);
        $this->appointment($other, ['name' => 'لغيري']);

        $response = $this->actingAs($mine)->getJson(self::DASH)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $new->id)
            ->assertJsonPath('data.1.id', (string) $old->id)
            ->assertJsonPath('data.0.name', 'جديد')
            ->assertJsonPath('data.0.countryCode', '970')
            ->assertJsonPath('data.0.datetime', '2026-11-18T08:00:00.000Z')
            ->assertJsonPath('data.0.reason', 'تطعيم')
            ->assertJsonPath('data.0.note', 'ملاحظة')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.1.reason', '')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonMissingPath('data.0.place_id');
    }

    public function test_index_paginates_with_a_cursor_filters_by_status_and_searches_by_name(): void
    {
        $owner = $this->makeOwner('clinic', 'paid');

        foreach (['علي', 'سارة', 'منى'] as $name) {
            $this->appointment($owner, ['name' => $name]);
        }
        $this->appointment($owner, ['name' => 'خالد', 'status' => DashboardAppointment::STATUS_CONFIRMED]);

        $page = $this->actingAs($owner)->getJson(self::DASH.'?limit=2')->assertOk();
        $page->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true)->assertJsonPath('meta.total', 4);

        $next = $this->actingAs($owner)->getJson(self::DASH.'?limit=2&cursor='.urlencode($page->json('meta.next_cursor')))->assertOk();
        $next->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', false);

        $this->actingAs($owner)->getJson(self::DASH.'?status=confirmed')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson(self::DASH.'?q='.urlencode('سار'))->assertOk()->assertJsonCount(1, 'data');
        // علامات LIKE تُعامل كنص عادي.
        $this->actingAs($owner)->getJson(self::DASH.'?q=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson(self::DASH.'?status=bogus')->assertStatus(422);
        $this->actingAs($owner)->getJson(self::DASH.'?limit=999')->assertStatus(422);
    }

    public function test_summary_counts_by_status_and_includes_deleted_in_used(): void
    {
        $owner = $this->makeOwner();
        $other = $this->makeOwner();
        $this->appointment($owner);
        $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CONFIRMED]);
        $gone = $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CANCELLED]);
        $gone->delete();
        $this->appointment($other);

        $this->actingAs($owner)->getJson(self::DASH.'/summary')->assertOk()
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.confirmed', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.limit', 5)
            ->assertJsonPath('data.used', 3);

        $paid = $this->makeOwner('clinic', 'paid');
        $this->actingAs($paid)->getJson(self::DASH.'/summary')->assertOk()->assertJsonPath('data.limit', null);
    }

    // ---------- الانتقالات ----------

    public function test_status_transitions_follow_the_contract(): void
    {
        $owner = $this->makeOwner();

        $a = $this->appointment($owner);
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$a->id, ['status' => 'confirmed'])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$a->id, ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        // الملغى نهائي.
        foreach (['confirmed', 'cancelled'] as $status) {
            $this->actingAs($owner)->patchJson(self::DASH.'/'.$a->id, ['status' => $status])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        }

        $b = $this->appointment($owner);
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$b->id, ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        // المؤكد لا يرجع معلّقاً ولا يؤكد مرتين.
        $c = $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CONFIRMED]);
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$c->id, ['status' => 'pending'])->assertStatus(422);
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$c->id, ['status' => 'confirmed'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->actingAs($owner)->patchJson(self::DASH.'/'.$c->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
    }

    public function test_confirming_a_conflicting_appointment_returns_409_until_the_first_is_cancelled(): void
    {
        $owner = $this->makeOwner();
        $first = $this->appointment($owner, ['name' => 'الأول']);
        $second = $this->appointment($owner, ['name' => 'الثاني']);

        $this->actingAs($owner)->patchJson(self::DASH.'/'.$first->id, ['status' => 'confirmed'])->assertOk();
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$second->id, ['status' => 'confirmed'])
            ->assertStatus(409)->assertJsonPath('code', 'slot_unavailable');
        $this->assertSame('pending', $second->refresh()->status);

        // رفض المعلّق ممكن دائماً.
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$first->id, ['status' => 'cancelled'])->assertOk();
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$second->id, ['status' => 'confirmed'])->assertOk();
    }

    public function test_confirming_a_different_time_does_not_conflict(): void
    {
        $owner = $this->makeOwner();
        $a = $this->appointment($owner);
        $b = $this->appointment($owner, ['scheduled_at' => Carbon::parse($this->at('11:00'))->utc()]);

        $this->actingAs($owner)->patchJson(self::DASH.'/'.$a->id, ['status' => 'confirmed'])->assertOk();
        $this->actingAs($owner)->patchJson(self::DASH.'/'.$b->id, ['status' => 'confirmed'])->assertOk();
    }

    // ---------- IDOR ----------

    public function test_an_owner_cannot_touch_another_clinics_appointments(): void
    {
        $mine = $this->makeOwner();
        $other = $this->makeOwner();
        $foreign = $this->appointment($other, ['name' => 'لغيري']);
        $foreignCancelled = $this->appointment($other, ['status' => DashboardAppointment::STATUS_CANCELLED]);

        $this->actingAs($mine)->patchJson(self::DASH.'/'.$foreign->id, ['status' => 'confirmed'])
            ->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->patchJson(self::DASH.'/'.$foreign->id, ['status' => 'cancelled'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson(self::DASH.'/'.$foreignCancelled->id)->assertStatus(404);
        $this->actingAs($mine)->patchJson(self::DASH.'/999999', ['status' => 'confirmed'])->assertStatus(404);

        $this->assertSame('pending', $foreign->refresh()->status);
        $this->assertNull($foreignCancelled->refresh()->deleted_at);
        $this->actingAs($mine)->getJson(self::DASH)->assertOk()->assertJsonCount(0, 'data');
    }

    // ---------- الحذف ----------

    public function test_only_cancelled_appointments_can_be_deleted_and_deletion_is_soft(): void
    {
        $owner = $this->makeOwner();
        $pending = $this->appointment($owner);
        $confirmed = $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CONFIRMED]);
        $cancelled = $this->appointment($owner, ['status' => DashboardAppointment::STATUS_CANCELLED]);

        $this->actingAs($owner)->deleteJson(self::DASH.'/'.$pending->id)->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->actingAs($owner)->deleteJson(self::DASH.'/'.$confirmed->id)->assertStatus(422);

        $this->actingAs($owner)->deleteJson(self::DASH.'/'.$cancelled->id)->assertNoContent();
        $this->assertSoftDeleted('dashboard_appointments', ['id' => $cancelled->id]);

        $this->actingAs($owner)->getJson(self::DASH)->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($owner)->deleteJson(self::DASH.'/'.$cancelled->id)->assertStatus(404);
    }

    public function test_deleting_a_place_deletes_its_appointments(): void
    {
        $owner = $this->makeOwner();
        $this->appointment($owner);

        $this->placeOf($owner)->forceDelete();

        $this->assertSame(0, DashboardAppointment::withTrashed()->count());
    }
}