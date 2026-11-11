<?php

namespace Tests\Feature;

use App\Jobs\SendOtpMessage;
use App\Jobs\SendPlaceStatusMessage;
use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use App\Services\WhatsApp\WhatsAppSendGuard;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '0591234567';

    private static int $counter = 0;

    private object $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new class implements WhatsAppSender
        {
            public array $sent = [];

            public function send(string $to, string $text, ?string $idempotencyKey = null): void
            {
                $this->sent[] = compact('to', 'text', 'idempotencyKey');
            }

            public function lastCode(): ?string
            {
                $last = end($this->sent);

                return $last && preg_match('/\b(\d{6})\b/', $last['text'], $m) ? $m[1] : null;
            }
        };
        $this->app->instance(WhatsAppSender::class, $this->sender);
    }

    // ---------------------------------------------------------------- helpers

    private function requestOtp(string $purpose, string $phone = self::PHONE, array $extra = [])
    {
        return $this->postJson('/api/auth/otp/request', array_merge(['purpose' => $purpose, 'phone' => $phone], $extra));
    }

    private function verifyOtp(string $purpose, string $code, string $phone = self::PHONE)
    {
        return $this->postJson('/api/auth/otp/verify', ['purpose' => $purpose, 'phone' => $phone, 'code' => $code]);
    }

    private function makeUser(array $attrs = []): User
    {
        return UserFactory::new()->create(array_merge(['user_phone' => self::PHONE], $attrs));
    }

    private function makePlace(User $owner, string $status = Place::STATUS_APPROVED): Place
    {
        $n = ++self::$counter;

        if (Area::count() === 0) {
            $region = new Regions;
            $region->region_slug = 'r-'.Str::random(6);
            $region->region_name_en = 'Gaza';
            $region->region_name_ar = 'غزة';
            $region->save();

            $area = new Area;
            $area->area_slug = 'Alrimal';
            $area->area_name_en = 'Al-Rimal';
            $area->area_name_ar = 'الرمال';
            $area->region_id = $region->id;
            $area->save();
        }

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street',
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => Area::first()->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->user_id = $owner->id;
        $place->place_approved_at = $status === Place::STATUS_APPROVED ? now() : null;
        $place->save();

        return $place;
    }

    // ---------------------------------------------------------------- التسجيل

    public function test_register_flow_creates_a_verified_user_and_logs_in(): void
    {
        $this->requestOtp('register', self::PHONE, ['full_name' => '  أحمد   محمد '])
            ->assertStatus(202)
            ->assertJsonPath('data.expires_in', 300)
            ->assertJsonPath('data.resend_after', 60);

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame('+970591234567', $this->sender->sent[0]['to']);
        $this->assertNull(User::where('user_phone', self::PHONE)->first(), 'لا حساب قبل التحقق من الكود');

        $this->verifyOtp('register', $this->sender->lastCode())
            ->assertCreated()
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.full_name', 'أحمد محمد')
            ->assertJsonPath('data.role', 'user');

        $user = User::where('user_phone', self::PHONE)->firstOrFail();
        $this->assertNotNull($user->user_phone_verified_at);
        $this->assertNotNull($user->user_last_login_at);
        $this->assertNull($user->user_password);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_register_with_existing_phone_is_refused_and_sends_nothing(): void
    {
        $this->makeUser();

        $this->requestOtp('register', self::PHONE, ['full_name' => 'Ali'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_EXISTS');

        $this->assertSame([], $this->sender->sent);
    }

    public function test_register_with_a_deleted_users_phone_is_refused(): void
    {
        $user = $this->makeUser();
        $user->delete();

        $this->requestOtp('register', self::PHONE, ['full_name' => 'Ali'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_EXISTS');
    }

    public function test_register_requires_a_name(): void
    {
        $this->requestOtp('register')->assertStatus(422)->assertJsonPath('code', 'VALIDATION')
            ->assertJsonStructure(['errors' => ['full_name']]);

        $this->requestOtp('register', self::PHONE, ['full_name' => str_repeat('a', 46)])->assertStatus(422);
        $this->assertSame([], $this->sender->sent);
    }

    public function test_phone_format_is_validated_and_arabic_digits_are_normalised(): void
    {
        foreach (['0521234567', '972521234567', '97259123456', '9725912345678', '9715912345678', '059123456', 'abc', ''] as $bad) {
            $this->requestOtp('login', $bad)->assertStatus(422)->assertJsonStructure(['errors' => ['phone']]);
        }

        $this->requestOtp('login', '٠٥٩ ١٢٣-٤٥٦٧')->assertStatus(202);
        $this->requestOtp('nope')->assertStatus(422)->assertJsonStructure(['errors' => ['purpose']]);
    }

    public function test_otp_message_is_valid_utf8_and_can_be_sent_as_json(): void
    {
        $this->makeUser();

        foreach ([60, 120, 300, 600, 900] as $ttl) {
            Cache::flush();
            config(['otp.ttl' => $ttl]);
            $this->sender->sent = [];

            $this->requestOtp('login')->assertStatus(202);

            $text = $this->sender->sent[0]['text'];
            $this->assertTrue(mb_check_encoding($text, 'UTF-8'), "UTF-8 غير صالح عند ttl={$ttl}");
            $this->assertNotFalse(json_encode(['text' => $text]), "json_encode فشل عند ttl={$ttl}");
        }
    }

    // ---------------------------------------------------------------- صيغ الأرقام (056/059 + 970/972)

    public function test_every_accepted_phone_format_registers_with_the_canonical_number_and_the_right_destination(): void
    {
        $cases = [
            // المدخل => [المخزّن بقاعدة البيانات, رقم الإرسال E.164]
            '0597379047' => ['0597379047', '+970597379047'],
            '+970567883165' => ['0567883165', '+970567883165'],
            '++972592194533' => ['972592194533', '+972592194533'],
            '972567883164' => ['972567883164', '+972567883164'],
            '00972599145431' => ['972599145431', '+972599145431'],
            '00970592000111' => ['0592000111', '+970592000111'],
        ];

        foreach ($cases as $input => [$stored, $to]) {
            $input = (string) $input;

            $this->requestOtp('register', $input, ['full_name' => 'Test User'])->assertStatus(202);
            $this->assertSame($to, end($this->sender->sent)['to'], "الوجهة لـ {$input}");

            $this->verifyOtp('register', $this->sender->lastCode(), $input)
                ->assertCreated()
                ->assertJsonPath('data.phone', $stored);

            $this->assertNotNull(User::where('user_phone', $stored)->first(), "المخزّن لـ {$input}");
        }
    }

    public function test_970_international_format_is_the_same_account_as_the_local_number(): void
    {
        $user = $this->makeUser(['user_phone' => '0597379047']);

        foreach (['970597379047', '+970597379047'] as $input) {
            Cache::flush();
            $this->requestOtp('login', $input)->assertStatus(202);
            $this->verifyOtp('login', $this->sender->lastCode(), $input)
                ->assertOk()
                ->assertJsonPath('data.id', $user->id);
        }

        $this->assertSame(1, User::where('user_phone', 'like', '%597379047')->count());
    }

    public function test_972_number_is_a_separate_account_from_the_local_number(): void
    {
        $this->makeUser(['user_phone' => '0592194533']);

        $this->requestOtp('login', '972592194533')->assertStatus(202);

        $this->assertSame([], $this->sender->sent, 'رقم 972 مختلف عن المحلي، فما في حساب مؤهل');
    }

    public function test_allowed_list_accepts_the_test_numbers_in_any_format(): void
    {
        $guard = new WhatsAppSendGuard(0, ['+970597379047', '++972592194533', '972567883164']);

        foreach (['0597379047', '972592194533', '972567883164'] as $stored) {
            $to = SendPlaceStatusMessage::toE164($stored);
            $this->assertNotNull($to);
            $guard->acquire($to);
        }

        $this->expectException(WhatsAppSendException::class);
        $guard->acquire((string) SendPlaceStatusMessage::toE164('0599145431'));
    }

    // ---------------------------------------------------------------- الدخول

    public function test_login_flow_for_an_existing_user(): void
    {
        $user = $this->makeUser();

        $this->requestOtp('login')->assertStatus(202);
        $this->verifyOtp('login', $this->sender->lastCode())
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertNotNull($user->fresh()->user_last_login_at);
        $this->assertNotNull($user->fresh()->user_phone_verified_at);
    }

    public function test_login_for_unknown_phone_looks_identical_but_sends_nothing(): void
    {
        $known = $this->makeUser(['user_phone' => '0592222222']);
        $a = $this->requestOtp('login', '0592222222');
        $b = $this->requestOtp('login', '0593333333');

        $this->assertSame($a->status(), $b->status());
        $this->assertSame($a->json(), $b->json());
        $this->assertCount(1, $this->sender->sent, 'رسالة واحدة فقط للرقم المسجّل');

        $this->verifyOtp('login', '123456', '0593333333')->assertStatus(422)->assertJsonPath('code', 'INVALID_CODE');
        $this->assertNotNull($known);
    }

    public function test_suspended_user_gets_no_code(): void
    {
        $this->makeUser(['user_status' => 'suspended']);

        $this->requestOtp('login')->assertStatus(202);
        $this->assertSame([], $this->sender->sent);
    }

    public function test_a_user_suspended_after_requesting_cannot_log_in(): void
    {
        $user = $this->makeUser();
        $this->requestOtp('login');
        $code = $this->sender->lastCode();

        $user->user_status = 'suspended';
        $user->save();

        $this->verifyOtp('login', $code)->assertStatus(422)->assertJsonPath('code', 'INVALID_CODE');
        $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    // ---------------------------------------------------------------- قواعد الكود

    public function test_wrong_code_is_rejected_and_five_misses_invalidate_the_code(): void
    {
        $this->makeUser();
        $this->requestOtp('login');
        $good = $this->sender->lastCode();
        $bad = $good === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->verifyOtp('login', $bad)->assertStatus(422)->assertJsonPath('code', 'INVALID_CODE');
        }

        $this->verifyOtp('login', $good)->assertStatus(422)->assertJsonPath('code', 'INVALID_CODE');
    }

    public function test_code_is_single_use(): void
    {
        $this->makeUser();
        $this->requestOtp('login');
        $code = $this->sender->lastCode();

        $this->verifyOtp('login', $code)->assertOk();
        $this->verifyOtp('login', $code)->assertStatus(422);
    }

    public function test_code_expires_after_the_ttl(): void
    {
        $this->makeUser();
        $this->requestOtp('login');
        $code = $this->sender->lastCode();

        $this->travel(301)->seconds();

        $this->verifyOtp('login', $code)->assertStatus(422)->assertJsonPath('code', 'INVALID_CODE');
    }

    public function test_code_for_one_purpose_does_not_work_for_another(): void
    {
        $this->makeUser();
        $this->requestOtp('login');

        $this->verifyOtp('register', $this->sender->lastCode())->assertStatus(422);
    }

    public function test_code_format_is_validated(): void
    {
        foreach (['12345', '1234567', 'abcdef', ''] as $bad) {
            $this->verifyOtp('login', $bad)->assertStatus(422)->assertJsonStructure(['errors' => ['code']]);
        }
    }

    public function test_the_code_is_stored_hashed_and_the_job_payload_is_encrypted(): void
    {
        $this->makeUser();
        $this->requestOtp('login');
        $code = $this->sender->lastCode();

        $entry = Cache::get('otp:code:'.hash('sha256', self::PHONE).':login');
        $this->assertNotNull($entry);
        $this->assertStringNotContainsString($code, json_encode($entry));
        $this->assertContains(ShouldBeEncrypted::class, class_implements(SendOtpMessage::class));
    }

    // ---------------------------------------------------------------- الفاصل والسقف

    public function test_resend_cooldown_then_a_new_code_replaces_the_old_one(): void
    {
        $this->makeUser();
        $this->requestOtp('login')->assertStatus(202);
        $first = $this->sender->lastCode();

        $this->requestOtp('login')->assertStatus(429)
            ->assertJsonPath('code', 'THROTTLED')
            ->assertJsonPath('reason', 'cooldown')
            ->assertHeader('Retry-After');

        $this->travel(61)->seconds();
        $this->requestOtp('login')->assertStatus(202);
        $second = $this->sender->lastCode();

        if ($first !== $second) {
            $this->verifyOtp('login', $first)->assertStatus(422);
        }
        $this->verifyOtp('login', $second)->assertOk();
    }

    public function test_daily_limit_per_phone(): void
    {
        config(['otp.daily_limit' => 2, 'otp.resend_cooldown' => 0]);
        $this->makeUser();

        $this->requestOtp('login')->assertStatus(202);
        $this->requestOtp('login')->assertStatus(202);
        $this->requestOtp('login')->assertStatus(429)->assertJsonPath('reason', 'daily_limit');
    }

    public function test_the_unknown_phone_is_throttled_exactly_like_a_known_one(): void
    {
        $this->requestOtp('login', '0594444444')->assertStatus(202);
        $this->requestOtp('login', '0594444444')->assertStatus(429)->assertJsonPath('reason', 'cooldown');
    }

    public function test_request_rate_limiter_per_phone(): void
    {
        config(['otp.daily_limit' => 100, 'otp.resend_cooldown' => 0]);
        $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->requestOtp('login')->assertStatus(202);
        }

        $this->requestOtp('login')->assertStatus(429);
    }

    // ---------------------------------------------------------------- الكود الثابت للتطوير

    public function test_fixed_code_works_in_testing_but_is_ignored_in_production(): void
    {
        config(['otp.fixed_code' => '123456', 'otp.resend_cooldown' => 0]);
        $this->makeUser();

        $this->requestOtp('login')->assertStatus(202);
        $this->assertSame('123456', $this->sender->lastCode());

        // خارج local/testing نستدعي الخدمة مباشرة (طلب HTTP بالإنتاج يفعّل CSRF فيصبح الاختبار غير ممثّل).
        $this->app->detectEnvironment(fn () => 'production');

        app(OtpService::class)->request(self::PHONE, 'login', true);
        $this->assertNotSame('123456', $this->sender->lastCode());

        $this->app->detectEnvironment(fn () => 'testing');
    }

    // ---------------------------------------------------------------- لوحة صاحب المحل

    public function test_dashboard_login_for_an_owner_with_an_approved_place_activates_it(): void
    {
        $owner = $this->makeUser(['user_role' => 'store_owner']);
        $place = $this->makePlace($owner);
        $this->assertNull($place->place_activated_at);

        $this->requestOtp('dashboard')->assertStatus(202);
        $this->assertStringContainsString('لوحة التحكم', $this->sender->sent[0]['text']);

        $this->verifyOtp('dashboard', $this->sender->lastCode())->assertOk()->assertJsonPath('data.role', 'store_owner');

        $this->assertNotNull($place->fresh()->place_activated_at);
        $this->getJson('/api/auth/me')->assertJsonPath('data.id', $owner->id);
    }

    public function test_dashboard_activation_time_is_not_overwritten_on_later_logins(): void
    {
        config(['otp.resend_cooldown' => 0]);
        $owner = $this->makeUser(['user_role' => 'store_owner']);
        $place = $this->makePlace($owner);

        $this->requestOtp('dashboard');
        $this->verifyOtp('dashboard', $this->sender->lastCode())->assertOk();
        $first = $place->fresh()->place_activated_at;

        $this->travel(2)->hours();
        $this->requestOtp('dashboard');
        $this->verifyOtp('dashboard', $this->sender->lastCode())->assertOk();

        $this->assertEquals($first, $place->fresh()->place_activated_at);
    }

    public function test_dashboard_code_is_not_sent_to_users_who_are_not_approved_owners(): void
    {
        $pending = $this->makeUser(['user_phone' => '0591111111', 'user_role' => 'store_owner']);
        $this->makePlace($pending, Place::STATUS_PENDING);
        $this->makeUser(['user_phone' => '0592222222']); // مستخدم عادي بلا محل
        $rejected = $this->makeUser(['user_phone' => '0593333333', 'user_role' => 'user']);
        $this->makePlace($rejected, Place::STATUS_REJECTED);

        foreach (['0591111111', '0592222222', '0593333333', '0599999999'] as $phone) {
            $this->requestOtp('dashboard', $phone)->assertStatus(202);
        }

        $this->assertSame([], $this->sender->sent);
    }

    public function test_dashboard_login_is_refused_if_the_place_is_rejected_after_the_code_was_sent(): void
    {
        $owner = $this->makeUser(['user_role' => 'store_owner']);
        $place = $this->makePlace($owner);

        $this->requestOtp('dashboard');
        $code = $this->sender->lastCode();

        $place->place_status = Place::STATUS_REJECTED;
        $place->save();

        $this->verifyOtp('dashboard', $code)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    // ---------------------------------------------------------------- الجلسة

    public function test_me_is_unauthorized_for_guests_and_logout_ends_the_session(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

        $user = $this->makeUser();
        $this->actingAs($user)->getJson('/api/auth/me')->assertJsonPath('data.id', $user->id);

        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest();
        $this->postJson('/api/auth/logout')->assertNoContent();
    }

    public function test_me_unauthorized_response_still_sets_the_xsrf_cookie(): void
    {
        // الفرونت يستدعي /me كأول طلب ليأخذ XSRF cookie قبل أي POST، ويجب أن تنزل مع رد الـ 401 أيضاً.
        $response = $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->assertNotNull($response->getCookie('XSRF-TOKEN', false));
    }

    public function test_me_never_exposes_password_or_internal_fields(): void
    {
        $user = $this->makeUser(['user_password' => 'secret-pass']);

        $json = $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->json('data');

        $this->assertSame(
            ['area_id', 'avatar_url', 'full_name', 'id', 'phone', 'points_total', 'preferred_lang', 'role', 'theme'],
            collect($json)->keys()->sort()->values()->all()
        );
    }

    public function test_an_eligible_request_queues_exactly_one_otp_job(): void
    {
        Queue::fake();
        $this->makeUser();

        $this->requestOtp('login')->assertStatus(202);

        Queue::assertPushed(SendOtpMessage::class, 1);
    }

    // ---------------------------------------------------------------- CORS

    public function test_cors_allows_only_configured_frontend_origins_with_credentials(): void
    {
        config(['cors.allowed_origins' => ['https://gazamarket.com']]);

        $this->withHeaders(['Origin' => 'https://gazamarket.com'])->getJson('/api/auth/me')
            ->assertHeader('Access-Control-Allow-Origin', 'https://gazamarket.com')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // مع origin واحد مسموح تُرجع المكتبة ذلك الـ origin دائماً، والمتصفح يرفض غير المطابق؛
        // المهم ألّا يُسمح أبداً للـ origin الغريب نفسه ولا بـ "*".
        $evil = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/auth/me');
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));

        config(['cors.allowed_origins' => []]);
        $none = $this->withHeaders(['Origin' => 'https://gazamarket.com'])->getJson('/api/auth/me');
        $this->assertNull($none->headers->get('Access-Control-Allow-Origin'));
    }
}