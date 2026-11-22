<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected bool $actAsAdmin = false;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__client-ip', fn (Request $request) => response($request->ip()));
    }

    private function requestThroughProxy(): string
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('/__client-ip')
            ->getContent();
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->assertSame('10.0.0.1', $this->requestThroughProxy());
    }

    public function test_forwarded_ip_is_used_for_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->assertSame('203.0.113.9', $this->requestThroughProxy());
    }

    public function test_the_default_config_trusts_no_proxy(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));
    }
}