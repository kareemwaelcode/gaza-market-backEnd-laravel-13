<?php

namespace Tests\Feature;

use App\Services\WhatsApp\LogWhatsAppSender;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use App\Services\WhatsApp\WhatsAppSendGuard;
use App\Services\WhatsApp\WuapiWhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** حماية الباقة (سقف يومي + قائمة أرقام) وربط المشغّل من الإعدادات. */
class WhatsAppGuardTest extends TestCase
{
    use RefreshDatabase;

    private function sender(WhatsAppSendGuard $guard): WuapiWhatsAppSender
    {
        return new WuapiWhatsAppSender('https://api.wuapi.dev', 'k', 'acc', 5, $guard);
    }

    private function sendExpectingFailure(WuapiWhatsAppSender $sender, string $to, bool $retryable = false): WhatsAppSendException
    {
        try {
            $sender->send($to, 'hi');
        } catch (WhatsAppSendException $e) {
            $this->assertSame($retryable, $e->retryable);

            return $e;
        }

        $this->fail('expected WhatsAppSendException');
    }

    public function test_daily_limit_blocks_extra_messages_before_any_http_call(): void
    {
        Cache::flush();
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);
        $sender = $this->sender(new WhatsAppSendGuard(2));

        $sender->send('+970591234567', 'a');
        $sender->send('+970591234568', 'b');
        $this->sendExpectingFailure($sender, '+970591234569');

        Http::assertSentCount(2);
    }

    public function test_failed_sends_do_not_consume_the_daily_limit(): void
    {
        Cache::flush();
        Http::fake(['api.wuapi.dev/*' => Http::sequence()
            ->push([], 500)
            ->push([], 400)
            ->push(['id' => 'm'], 202)]);
        $sender = $this->sender(new WhatsAppSendGuard(1));

        $this->sendExpectingFailure($sender, '+970591234567', retryable: true);
        $this->sendExpectingFailure($sender, '+970591234567');
        $sender->send('+970591234567', 'ok'); // لا يزال السقف متاحاً

        Http::assertSentCount(3);
    }

    public function test_zero_means_no_daily_limit(): void
    {
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);
        $sender = $this->sender(new WhatsAppSendGuard(0));

        for ($i = 0; $i < 5; $i++) {
            $sender->send('+970591234567', 'x');
        }

        Http::assertSentCount(5);
    }

    public function test_allowlist_blocks_other_numbers_and_accepts_any_format_of_the_same_number(): void
    {
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);
        $sender = $this->sender(new WhatsAppSendGuard(0, ['0591234567', '+972 50-123-4567']));

        $sender->send('+970591234567', 'ok');
        $sender->send('+972501234567', 'ok');
        $this->sendExpectingFailure($sender, '+970599999999');

        Http::assertSentCount(2);
    }

    public function test_the_blocked_message_never_contains_the_number(): void
    {
        $e = $this->sendExpectingFailure($this->sender(new WhatsAppSendGuard(0, ['0591234567'])), '+970599999999');

        $this->assertStringNotContainsString('599999999', $e->getMessage());
    }

    public function test_guard_is_built_from_config(): void
    {
        config(['whatsapp.driver' => 'wuapi', 'whatsapp.daily_limit' => 1, 'whatsapp.allowed_to' => ['0591234567']]);
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);
        config(['whatsapp.wuapi.api_key' => 'k', 'whatsapp.wuapi.account_id' => 'acc']);
        Cache::flush();

        $sender = app(WhatsAppSender::class);
        $this->assertInstanceOf(WuapiWhatsAppSender::class, $sender);

        $sender->send('+970591234567', 'ok');
        $this->sendExpectingFailure($sender, '+970591234567'); // السقف 1
        Http::assertSentCount(1);
    }

    public function test_wuapi_request_has_the_expected_shape(): void
    {
        Http::fake(['api.wuapi.dev/*' => Http::response(['id' => 'm'], 202)]);

        (new WuapiWhatsAppSender('https://api.wuapi.dev', 'test-key', 'acc-1', 5))
            ->send('+970591234567', 'hello', 'idem-1');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.wuapi.dev/v1/messages'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->hasHeader('Idempotency-Key', 'idem-1')
                && $request->data() === ['accountId' => 'acc-1', 'to' => '+970591234567', 'text' => 'hello'];
        });
    }

    public function test_wuapi_refuses_to_send_when_not_configured(): void
    {
        Http::fake();

        $e = $this->sendExpectingFailure(new WuapiWhatsAppSender('https://api.wuapi.dev', null, 'acc', 5), '+970591234567');

        $this->assertStringContainsString('not configured', $e->getMessage());
        Http::assertNothingSent();
    }

    public function test_log_driver_is_the_default_so_nothing_is_sent_without_configuration(): void
    {
        $this->assertSame('log', config('whatsapp.driver'));
        $this->assertInstanceOf(LogWhatsAppSender::class, app(WhatsAppSender::class));
    }

    public function test_env_example_documents_the_whatsapp_and_otp_variables(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        foreach (['WHATSAPP_DRIVER', 'WUAPI_API_KEY', 'WUAPI_ACCOUNT_ID', 'WHATSAPP_DAILY_LIMIT', 'WHATSAPP_ALLOWED_TO', 'OWNER_DASHBOARD_URL', 'OTP_FIXED_CODE', 'FRONTEND_URLS'] as $name) {
            $this->assertStringContainsString($name, $env);
        }

        $this->assertDoesNotMatchRegularExpression('/^WUAPI_API_KEY=.+/m', $env, 'لا أسرار في القالب');
    }
}