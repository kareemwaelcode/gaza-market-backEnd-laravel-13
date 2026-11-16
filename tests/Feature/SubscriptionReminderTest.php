<?php

namespace Tests\Feature;

use App\Jobs\SendSubscriptionReminder;
use App\Models\Area;
use App\Models\Place;
use App\Models\PlaceSubscription;
use App\Models\Regions;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionReminderTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00:00', 'UTC'));
        config(['subscription.reminder.days_before' => 3]);

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $status = 'active'): User
    {
        $user = new User;
        $user->user_phone = '059'.str_pad((string) ++self::$counter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Owner '.self::$counter;
        $user->user_role = 'store_owner';
        $user->user_status = $status;
        $user->save();

        return $user;
    }

    private function makePlace(?User $owner = null): Place
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
        $place->place_status = Place::STATUS_APPROVED;
        $place->place_approved_at = now();
        $place->user_id = ($owner ?? $this->makeUser())->id;
        $place->save();

        return $place;
    }

    private function makeSub(Place $place, Carbon $endsAt, array $extra = []): PlaceSubscription
    {
        $sub = new PlaceSubscription;
        $sub->forceFill(array_merge([
            'place_id' => $place->id,
            'subscription_amount' => 99,
            'subscription_currency' => 'ILS',
            'subscription_period_months' => 2,
            'subscription_starts_at' => $endsAt->copy()->subMonths(2),
            'subscription_ends_at' => $endsAt,
        ], $extra))->save();

        return $sub;
    }

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

    public function test_command_queues_only_subscriptions_inside_the_window(): void
    {
        Queue::fake();

        $inside = $this->makeSub($this->makePlace(), now()->addDays(2));
        $this->makeSub($this->makePlace(), now()->addDays(10));
        $this->makeSub($this->makePlace(), now()->addDays(2), ['subscription_reminder_sent_at' => now()]);
        $this->makeSub($this->makePlace(), now()->addDays(2), ['subscription_cancelled_at' => now()]);
        $this->makeSub($this->makePlace(), now()->subDay());

        $renewed = $this->makePlace();
        $this->makeSub($renewed, now()->addDays(2));
        $this->makeSub($renewed, now()->addDays(60));

        Artisan::call('subscriptions:send-reminders');

        Queue::assertPushed(SendSubscriptionReminder::class, 1);
        Queue::assertPushed(
            SendSubscriptionReminder::class,
            fn ($job) => $job->subscriptionId === $inside->id,
        );
    }

    public function test_job_sends_the_message_once_and_marks_the_reminder(): void
    {
        $sender = $this->fakeSender();
        $place = $this->makePlace();
        $sub = $this->makeSub($place, now()->addDays(2));

        (new SendSubscriptionReminder($sub->id))->handle($sender);
        (new SendSubscriptionReminder($sub->id))->handle($sender);

        $this->assertCount(1, $sender->sent);
        $this->assertStringStartsWith('+970', $sender->sent[0]['to']);
        $this->assertSame("subscription-reminder-{$sub->id}", $sender->sent[0]['idempotencyKey']);
        $this->assertStringContainsString($place->place_name, $sender->sent[0]['text']);
        $this->assertStringContainsString('يومين', $sender->sent[0]['text']);
        $this->assertStringContainsString('99 شيكل', $sender->sent[0]['text']);
        $this->assertNotNull($sub->fresh()->subscription_reminder_sent_at);
    }

    public function test_retryable_failure_keeps_the_reminder_pending(): void
    {
        $sender = $this->fakeSender(WhatsAppSendException::retryable('timeout'));
        $sub = $this->makeSub($this->makePlace(), now()->addDays(2));

        try {
            (new SendSubscriptionReminder($sub->id))->handle($sender);
            $this->fail('Expected a retryable exception.');
        } catch (WhatsAppSendException $e) {
            $this->assertTrue($e->retryable);
        }

        $this->assertNull($sub->fresh()->subscription_reminder_sent_at);
    }

    public function test_suspended_owner_gets_nothing(): void
    {
        $sender = $this->fakeSender();
        $sub = $this->makeSub($this->makePlace($this->makeUser('suspended')), now()->addDays(2));

        (new SendSubscriptionReminder($sub->id))->handle($sender);

        $this->assertCount(0, $sender->sent);
        $this->assertNull($sub->fresh()->subscription_reminder_sent_at);
    }

    public function test_renewal_after_the_reminder_starts_a_fresh_reminder_cycle(): void
    {
        $sender = $this->fakeSender();
        $place = $this->makePlace();
        $first = $this->makeSub($place, now()->addDays(2));

        (new SendSubscriptionReminder($first->id))->handle($sender);
        $this->assertNotNull($first->fresh()->subscription_reminder_sent_at);

        $renewal = $this->makeSub($place, now()->addDays(62));

        $this->assertNull($renewal->fresh()->subscription_reminder_sent_at);
        $this->assertFalse(PlaceSubscription::needingReminder()->whereKey($first->id)->exists());
    }
}