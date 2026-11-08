<?php

namespace Tests\Feature;

use App\Jobs\SendPlaceStatusMessage;
use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use App\Services\WhatsApp\LogWhatsAppSender;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use App\Services\WhatsApp\WuapiWhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaceOwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

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
        $this->area->area_slug = 'Alrimal';
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    private function makeUser(string $role = 'user', string $status = 'active'): User
    {
        $user = new User;
        $user->user_phone = '059'.str_pad((string) ++self::$counter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Owner '.self::$counter;
        $user->user_role = $role;
        $user->user_status = $status;
        $user->save();

        return $user;
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
        if ($status === Place::STATUS_APPROVED) {
            $place->place_approved_at = now();
        }
        $place->save();

        return $place;
    }

    /** مرسل وهمي يسجّل ما أُرسل ويمكن جعله يفشل. */
    private function fakeSender(?WhatsAppSendException $failWith = null): object
    {
        $sender = new class implements WhatsAppSender
        {
            public array $sent = [];

            public ?WhatsAppSendException $failWith = null;

            public function send(string $to, string $text, ?string $idempotencyKey = null): void
            {
                if ($this->failWith !== null) {
                    throw $this->failWith;
                }

                $this->sent[] = compact('to', 'text', 'idempotencyKey');
            }
        };
        $sender->failWith = $failWith;
        $this->app->instance(WhatsAppSender::class, $sender);

        return $sender;
    }

    // ---------------------------------------------------------------- القبول والرفض

    public function test_approve_promotes_user_and_dispatches_one_message(): void
    {
        Queue::fake();
        $place = $this->makePlace();

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();

        $this->assertSame('store_owner', $place->user()->first()->user_role);
        Queue::assertPushed(SendPlaceStatusMessage::class, 1);
        Queue::assertPushed(
            SendPlaceStatusMessage::class,
            fn ($job) => $job->placeId === $place->id && $job->status === Place::STATUS_APPROVED,
        );
    }

    public function test_second_approve_is_refused_and_sends_nothing_more(): void
    {
        Queue::fake();
        $place = $this->makePlace();

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();
        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertStatus(409);

        Queue::assertPushed(SendPlaceStatusMessage::class, 1);
    }

    public function test_approve_keeps_an_existing_store_owner_role(): void
    {
        Queue::fake();
        $owner = $this->makeUser('store_owner');
        $place = $this->makePlace($owner);

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();

        $this->assertSame('store_owner', $owner->fresh()->user_role);
    }

    public function test_reject_of_pending_place_dispatches_message_and_leaves_role_alone(): void
    {
        Queue::fake();
        $place = $this->makePlace();

        $this->postJson("/cms/admins/place_reject/{$place->id}")->assertOk();

        $this->assertSame('user', $place->user()->first()->user_role);
        Queue::assertPushed(
            SendPlaceStatusMessage::class,
            fn ($job) => $job->placeId === $place->id && $job->status === Place::STATUS_REJECTED,
        );
    }

    public function test_reject_of_approved_place_revokes_store_owner_role(): void
    {
        Queue::fake();
        $owner = $this->makeUser('store_owner');
        $place = $this->makePlace($owner, Place::STATUS_APPROVED);

        $this->postJson("/cms/admins/place_reject/{$place->id}")->assertOk();

        $this->assertSame('user', $owner->fresh()->user_role);
    }

    public function test_refused_transition_changes_neither_role_nor_queue(): void
    {
        Queue::fake();
        $owner = $this->makeUser('store_owner');
        $place = $this->makePlace($owner, Place::STATUS_REJECTED);

        $this->postJson("/cms/admins/place_reject/{$place->id}")->assertStatus(409);

        $this->assertSame('store_owner', $owner->fresh()->user_role);
        Queue::assertNothingPushed();
    }

    public function test_whatsapp_failure_does_not_undo_the_approval(): void
    {
        // طابور sync: الـ Job يعمل داخل الطلب، والإرسال يفشل بشكل دائم.
        $this->fakeSender(WhatsAppSendException::permanent('boom'));
        $place = $this->makePlace();

        $this->postJson("/cms/admins/place_approve/{$place->id}")->assertOk();

        $this->assertSame(Place::STATUS_APPROVED, $place->fresh()->place_status);
    }

    // ---------------------------------------------------------------- الـ Job

    public function test_job_sends_login_link_to_the_registered_phone_in_e164(): void
    {
        config(['owner_dashboard.base_url' => 'https://gazamarket.com/places/']);
        $sender = $this->fakeSender();
        $owner = $this->makeUser();
        $owner->user_phone = '0591234567';
        $owner->save();
        $place = $this->makePlace($owner, Place::STATUS_APPROVED);

        (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->handle($sender);

        $this->assertCount(1, $sender->sent);
        $this->assertSame('+970591234567', $sender->sent[0]['to']);
        $this->assertStringContainsString($place->place_name, $sender->sent[0]['text']);
        $this->assertStringContainsString('https://gazamarket.com/places/login', $sender->sent[0]['text']);
        $this->assertStringNotContainsString('/dashboard', $sender->sent[0]['text']);
        $this->assertStringStartsWith("place-{$place->id}-approved-", $sender->sent[0]['idempotencyKey']);
    }

    public function test_rejection_message_has_no_login_link(): void
    {
        $sender = $this->fakeSender();
        $place = $this->makePlace(null, Place::STATUS_REJECTED);

        (new SendPlaceStatusMessage($place->id, Place::STATUS_REJECTED))->handle($sender);

        $this->assertCount(1, $sender->sent);
        $this->assertStringNotContainsString('http', $sender->sent[0]['text']);
    }

    public function test_stale_job_is_skipped_when_status_changed_meanwhile(): void
    {
        $sender = $this->fakeSender();
        $place = $this->makePlace(null, Place::STATUS_REJECTED);

        (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->handle($sender);

        $this->assertSame([], $sender->sent);
    }

    public function test_job_is_skipped_for_missing_place_and_inactive_user(): void
    {
        $sender = $this->fakeSender();
        (new SendPlaceStatusMessage(99999, Place::STATUS_APPROVED))->handle($sender);

        $suspended = $this->makeUser('user', 'suspended');
        $place = $this->makePlace($suspended, Place::STATUS_APPROVED);
        (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->handle($sender);

        $this->assertSame([], $sender->sent);
    }

    public function test_job_is_skipped_for_an_invalid_phone(): void
    {
        $sender = $this->fakeSender();
        $owner = $this->makeUser();
        $owner->user_phone = '12ab';
        $owner->save();
        $place = $this->makePlace($owner, Place::STATUS_APPROVED);

        (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->handle($sender);

        $this->assertSame([], $sender->sent);
    }

    public function test_retryable_failure_is_rethrown_so_the_queue_retries(): void
    {
        $sender = $this->fakeSender(WhatsAppSendException::retryable('temporary'));
        $place = $this->makePlace(null, Place::STATUS_APPROVED);

        $this->expectException(WhatsAppSendException::class);

        (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->handle($sender);
    }

    public function test_permanent_failure_fails_the_job_without_retrying(): void
    {
        $sender = $this->fakeSender(WhatsAppSendException::permanent('bad request'));
        $place = $this->makePlace(null, Place::STATUS_APPROVED);

        $job = (new SendPlaceStatusMessage($place->id, Place::STATUS_APPROVED))->withFakeQueueInteractions();
        $job->handle($sender);

        $job->assertFailed();
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('+970591234567', SendPlaceStatusMessage::toE164('0591234567'));
        $this->assertSame('+970591234567', SendPlaceStatusMessage::toE164('970591234567'));
        $this->assertSame('+972591234567', SendPlaceStatusMessage::toE164('+972 59-123-4567'));
        $this->assertSame('+970591234567', SendPlaceStatusMessage::toE164('00970591234567'));
        $this->assertNull(SendPlaceStatusMessage::toE164('abc'));
        $this->assertNull(SendPlaceStatusMessage::toE164('0591'));
    }

    // ---------------------------------------------------------------- المشغّلون

    public function test_driver_binding_follows_config(): void
    {
        config(['whatsapp.driver' => 'log']);
        $this->assertInstanceOf(LogWhatsAppSender::class, app(WhatsAppSender::class));

        config(['whatsapp.driver' => 'wuapi']);
        $this->assertInstanceOf(WuapiWhatsAppSender::class, app(WhatsAppSender::class));

        config(['whatsapp.driver' => 'nope']);
        $this->expectException(\InvalidArgumentException::class);
        app(WhatsAppSender::class);
    }

    public function test_log_driver_never_logs_the_text_or_the_full_number(): void
    {
        Log::spy();

        (new LogWhatsAppSender)->send('+970591234567', 'secret-text', 'key-1');

        Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $context) {
            return $context['to'] === '+97059*******'
                && ! str_contains(json_encode($context), 'secret-text')
                && ! str_contains(json_encode($context), '591234567');
        });
    }

    public function test_wuapi_sender_posts_the_expected_request(): void
    {
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'msg_1', 'status' => 'queued'], 202)]);

        (new WuapiWhatsAppSender('https://api.wuapi.dev', 'wu_live_test', 'acc_1', 5))
            ->send('+970591234567', 'hello', 'idem-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.wuapi.dev/v1/messages'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer wu_live_test')
                && $request->hasHeader('Idempotency-Key', 'idem-1')
                && $request['accountId'] === 'acc_1'
                && $request['to'] === '+970591234567'
                && $request['text'] === 'hello';
        });
    }

    public function test_wuapi_sender_classifies_failures(): void
    {
        $cases = [[500, true], [503, true], [429, true], [400, false], [401, false], [404, false], [422, false]];

        $sequence = Http::sequence();
        foreach ($cases as [$status]) {
            $sequence->push(['error' => 'x'], $status);
        }
        Http::fake(['api.wuapi.dev/*' => $sequence]);

        $sender = new WuapiWhatsAppSender('https://api.wuapi.dev', 'k', 'acc_1', 5);

        foreach ($cases as [$status, $retryable]) {
            try {
                $sender->send('+970591234567', 'hi');
                $this->fail("HTTP {$status} should have thrown.");
            } catch (WhatsAppSendException $e) {
                $this->assertSame($retryable, $e->retryable, "HTTP {$status}");
                $this->assertStringContainsString((string) $status, $e->getMessage());
                $this->assertStringNotContainsString('error', $e->getMessage());
            }
        }
    }

    public function test_wuapi_sender_without_configuration_fails_permanently_and_sends_nothing(): void
    {
        Http::fake();

        foreach ([[null, 'acc'], ['key', null], ['', 'acc']] as [$key, $account]) {
            try {
                (new WuapiWhatsAppSender('https://api.wuapi.dev', $key, $account))->send('+970591234567', 'hi');
                $this->fail('Should have thrown.');
            } catch (WhatsAppSendException $e) {
                $this->assertFalse($e->retryable);
            }
        }

        Http::assertNothingSent();
    }

    public function test_connection_failure_is_retryable(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        try {
            (new WuapiWhatsAppSender('https://api.wuapi.dev', 'k', 'acc'))->send('+970591234567', 'hi');
            $this->fail('Should have thrown.');
        } catch (WhatsAppSendException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    // ---------------------------------------------------------------- التسجيل

    public function test_registration_without_whatsapp_is_a_validation_error_not_a_server_error(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/places', [
            'type' => 'cafe',
            'name' => 'Cafe',
            'region' => 'Alrimal',
            'address' => 'Street',
            'phone' => '0561234567',
        ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['whatsapp']]);

        $this->assertSame(0, Place::count());
    }
}