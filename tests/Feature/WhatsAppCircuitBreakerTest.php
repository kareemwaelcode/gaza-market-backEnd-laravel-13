<?php

namespace Tests\Feature;

use App\Jobs\SendOtpMessage;
use App\Services\WhatsApp\WhatsAppCircuitBreaker;
use App\Services\WhatsApp\WhatsAppCircuitOpenException;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use App\Services\WhatsApp\WhatsAppSendGuard;
use App\Services\WhatsApp\WuapiWhatsAppSender;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WhatsAppCircuitBreakerTest extends TestCase
{
    private const TO = '+970591234567';

    private function breaker(): WhatsAppCircuitBreaker
    {
        return new WhatsAppCircuitBreaker(5, 60, 120);
    }

    private function sender(?WhatsAppCircuitBreaker $breaker = null, ?WhatsAppSendGuard $guard = null): WuapiWhatsAppSender
    {
        return new WuapiWhatsAppSender('https://api.wuapi.dev', 'k', 'acc', 5, $guard, $breaker ?? $this->breaker());
    }

    private function sendExpectingFailure(WuapiWhatsAppSender $sender, bool $retryable): WhatsAppSendException
    {
        try {
            $sender->send(self::TO, 'hi');
        } catch (WhatsAppSendException $e) {
            $this->assertNotInstanceOf(WhatsAppCircuitOpenException::class, $e);
            $this->assertSame($retryable, $e->retryable);

            return $e;
        }

        $this->fail('expected WhatsAppSendException');
    }

    private function sendExpectingOpen(WuapiWhatsAppSender $sender): WhatsAppCircuitOpenException
    {
        try {
            $sender->send(self::TO, 'hi');
        } catch (WhatsAppCircuitOpenException $e) {
            $this->assertTrue($e->retryable);

            return $e;
        }

        $this->fail('expected WhatsAppCircuitOpenException');
    }

    private function sequence(int $failures, int $status = 503, int $successes = 0): void
    {
        $sequence = Http::sequence();

        for ($i = 0; $i < $failures; $i++) {
            $sequence->push([], $status);
        }

        for ($i = 0; $i < $successes; $i++) {
            $sequence->push(['id' => 'm'], 202);
        }

        Http::fake(['api.wuapi.dev/*' => $sequence]);
    }

    public function test_circuit_opens_after_the_threshold_of_provider_failures(): void
    {
        $this->sequence(5);
        $sender = $this->sender();

        for ($i = 0; $i < 5; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $open = $this->sendExpectingOpen($sender);

        $this->assertGreaterThanOrEqual(1, $open->retryAfter);
        $this->assertLessThanOrEqual(120, $open->retryAfter);
        Http::assertSentCount(5);
    }

    public function test_connection_failures_count_towards_the_threshold(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));
        $sender = $this->sender();

        for ($i = 0; $i < 5; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->sendExpectingOpen($sender);
    }

    public function test_rate_limit_responses_count_as_provider_failures(): void
    {
        $this->sequence(5, 429);
        $sender = $this->sender();

        for ($i = 0; $i < 5; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->sendExpectingOpen($sender);
    }

    public function test_client_errors_never_open_the_circuit(): void
    {
        $this->sequence(6, 400);
        $sender = $this->sender();

        for ($i = 0; $i < 6; $i++) {
            $this->sendExpectingFailure($sender, retryable: false);
        }

        Http::assertSentCount(6);
    }

    public function test_guard_refusals_do_not_count_as_provider_failures(): void
    {
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);
        $sender = $this->sender(null, new WhatsAppSendGuard(0, ['970599999999']));

        for ($i = 0; $i < 6; $i++) {
            $this->sendExpectingFailure($sender, retryable: false);
        }

        Http::assertNothingSent();

        $sender->send('+970599999999', 'hi');

        Http::assertSentCount(1);
    }

    public function test_a_successful_probe_closes_the_circuit(): void
    {
        $this->sequence(5, 503, 2);
        $sender = $this->sender();

        for ($i = 0; $i < 5; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->sendExpectingOpen($sender);

        $this->travel(121)->seconds();

        $sender->send(self::TO, 'probe');
        $sender->send(self::TO, 'after');

        Http::assertSentCount(7);
    }

    public function test_a_failed_probe_reopens_the_circuit(): void
    {
        $this->sequence(6);
        $sender = $this->sender();

        for ($i = 0; $i < 5; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->travel(121)->seconds();

        $this->sendExpectingFailure($sender, retryable: true);
        $this->sendExpectingOpen($sender);

        Http::assertSentCount(6);
    }

    public function test_only_one_probe_is_allowed_while_half_open(): void
    {
        $breaker = $this->breaker();

        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure();
        }

        $this->travel(121)->seconds();

        $breaker->ensureAvailable();

        $this->expectException(WhatsAppCircuitOpenException::class);

        $breaker->ensureAvailable();
    }

    public function test_a_success_resets_the_failure_count(): void
    {
        $sequence = Http::sequence();

        for ($i = 0; $i < 4; $i++) {
            $sequence->push([], 503);
        }

        $sequence->push(['id' => 'm'], 202);

        for ($i = 0; $i < 4; $i++) {
            $sequence->push([], 503);
        }

        $sequence->push(['id' => 'm'], 202);

        Http::fake(['api.wuapi.dev/*' => $sequence]);
        $sender = $this->sender();

        for ($i = 0; $i < 4; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $sender->send(self::TO, 'ok');

        for ($i = 0; $i < 4; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $sender->send(self::TO, 'ok');

        Http::assertSentCount(10);
    }

    public function test_failures_outside_the_window_are_forgotten(): void
    {
        $this->sequence(8, 503, 1);
        $sender = $this->sender();

        for ($i = 0; $i < 4; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->travel(61)->seconds();

        for ($i = 0; $i < 4; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $sender->send(self::TO, 'ok');

        Http::assertSentCount(9);
    }

    public function test_a_disabled_breaker_never_opens(): void
    {
        $this->sequence(10);
        $sender = $this->sender(new WhatsAppCircuitBreaker(5, 60, 120, null, false));

        for ($i = 0; $i < 10; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        Http::assertSentCount(10);
    }

    public function test_sending_still_works_when_the_cache_is_down(): void
    {
        Exceptions::fake();
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);

        $broken = Mockery::mock(Repository::class)->shouldIgnoreMissing();
        $broken->shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        Cache::partialMock()->shouldReceive('store')->andReturn($broken);

        $this->sender()->send(self::TO, 'hi');

        Http::assertSentCount(1);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_breaker_is_built_from_config_and_wired_into_the_wuapi_sender(): void
    {
        $this->assertTrue(config('whatsapp.circuit.enabled'));
        $this->assertSame(5, config('whatsapp.circuit.threshold'));
        $this->assertSame(60, config('whatsapp.circuit.window'));
        $this->assertSame(120, config('whatsapp.circuit.open_seconds'));

        config([
            'whatsapp.driver' => 'wuapi',
            'whatsapp.wuapi.base_url' => 'https://api.wuapi.dev',
            'whatsapp.wuapi.api_key' => 'k',
            'whatsapp.wuapi.account_id' => 'acc',
            'whatsapp.circuit.threshold' => 2,
        ]);

        $this->assertInstanceOf(WuapiWhatsAppSender::class, app(WhatsAppSender::class));

        $this->sequence(2);
        $sender = app(WhatsAppSender::class);

        for ($i = 0; $i < 2; $i++) {
            $this->sendExpectingFailure($sender, retryable: true);
        }

        $this->sendExpectingOpen($sender);
    }

    public function test_env_example_documents_the_circuit_variables(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        foreach (['WHATSAPP_CIRCUIT_ENABLED', 'WHATSAPP_CIRCUIT_THRESHOLD', 'WHATSAPP_CIRCUIT_WINDOW', 'WHATSAPP_CIRCUIT_OPEN_SECONDS', 'WHATSAPP_CIRCUIT_STORE'] as $name) {
            $this->assertStringContainsString($name, $env);
        }
    }

    private function openSender(int $retryAfter): WhatsAppSender
    {
        return new class($retryAfter) implements WhatsAppSender
        {
            public function __construct(private int $retryAfter) {}

            public function send(string $to, string $text, ?string $idempotencyKey = null): void
            {
                throw new WhatsAppCircuitOpenException($this->retryAfter);
            }
        };
    }

    public function test_otp_job_is_released_while_the_circuit_is_open(): void
    {
        $this->freezeTime();

        $job = (new SendOtpMessage('0591234567', 'login', '123456', now()->timestamp))->withFakeQueueInteractions();
        $job->handle($this->openSender(30));

        $job->assertReleased(30);
        $job->assertNotFailed();
    }

    public function test_otp_job_release_delay_never_passes_the_code_expiry(): void
    {
        $this->freezeTime();

        $job = (new SendOtpMessage('0591234567', 'login', '123456', now()->timestamp - ((int) config('otp.ttl') - 10)))->withFakeQueueInteractions();
        $job->handle($this->openSender(120));

        $job->assertReleased(10);
    }

    public function test_otp_job_retry_settings_count_only_real_exceptions(): void
    {
        $issuedAt = now()->timestamp;
        $job = new SendOtpMessage('0591234567', 'login', '123456', $issuedAt);

        $this->assertSame(3, $job->maxExceptions);
        $this->assertFalse(property_exists($job, 'tries'));
        $this->assertSame($issuedAt + (int) config('otp.ttl') + 60, $job->retryUntil()->getTimestamp());
    }
}