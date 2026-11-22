<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * اختبار أمر redis:check بدون Redis حقيقي: الاتصالات مُحاكاة.
 * ملاحظة: هاد بيثبت سلوك الأمر نفسه، مش سلامة Redis الحي (شغّل php artisan redis:check يدويًا لهاد).
 */
class RedisCheckCommandTest extends TestCase
{
    public function test_reports_ok_when_both_connections_answer(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->twice()->andReturn(true);

        Redis::shouldReceive('connection')->with('default')->once()->andReturn($connection);
        Redis::shouldReceive('connection')->with('cache')->once()->andReturn($connection);

        $this->artisan('redis:check')
            ->expectsOutputToContain('Redis connection [default]: OK')
            ->expectsOutputToContain('Redis connection [cache]: OK')
            ->assertExitCode(0);
    }

    public function test_fails_without_leaking_the_exception_message(): void
    {
        $healthy = Mockery::mock();
        $healthy->shouldReceive('ping')->once()->andReturn(true);

        Redis::shouldReceive('connection')->with('default')
            ->once()
            ->andThrow(new RuntimeException('secret-password-123'));
        Redis::shouldReceive('connection')->with('cache')->once()->andReturn($healthy);

        // الاتصال التاني بينفحص حتى لو الأول فشل، والرسالة الأصلية ما بتنطبع.
        $this->artisan('redis:check')
            ->expectsOutputToContain('Redis connection [default]: FAILED (RuntimeException)')
            ->expectsOutputToContain('Redis connection [cache]: OK')
            ->doesntExpectOutputToContain('secret-password-123')
            ->assertExitCode(1);
    }

    public function test_warns_when_the_client_is_not_predis(): void
    {
        config(['database.redis.client' => 'phpredis']);

        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->twice()->andReturn(true);

        Redis::shouldReceive('connection')->twice()->andReturn($connection);

        $this->artisan('redis:check')
            ->expectsOutputToContain('REDIS_CLIENT=predis is the agreed client')
            ->assertExitCode(0);
    }
}
