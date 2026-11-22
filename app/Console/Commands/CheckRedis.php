<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CheckRedis extends Command
{
    protected $signature = 'redis:check';

    protected $description = 'يفحص اتصال Redis ويبيّن أي أجزاء (كاش/جلسات/طوابير) مضبوطة عليه';

    // الاتصالات المعرّفة بـ config/database.php: default (DB 0) و cache (DB 1).
    private const CONNECTIONS = ['default', 'cache'];

    public function handle(): int
    {
        $this->reportConfiguration();

        $failed = false;

        foreach (self::CONNECTIONS as $name) {
            try {
                Redis::connection($name)->ping();

                $this->info("Redis connection [{$name}]: OK");
            } catch (Throwable $e) {
                $failed = true;

                // نطبع اسم الاستثناء بس: رسالته ممكن تحتوي عنوان الاتصال أو بيانات حساسة.
                $this->error("Redis connection [{$name}]: FAILED (".$e::class.')');
            }
        }

        if ($failed) {
            $this->line('Check that the Redis service is running and that REDIS_HOST / REDIS_PORT / REDIS_PASSWORD in .env are correct.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportConfiguration(): void
    {
        $client = (string) config('database.redis.client');

        $this->line("Redis client: {$client}");

        // القرار المحسوم: predis (الحزمة منزّلة بالـ composer وما بتحتاج امتداد PHP).
        if ($client !== 'predis') {
            $this->warn('REDIS_CLIENT=predis is the agreed client; phpredis needs the PHP extension installed on the server.');
        }

        $cacheStore = (string) config('cache.default');
        $apiCacheStore = (string) (config('prices.cache.store') ?: $cacheStore);
        $queue = (string) config('queue.default');
        $session = (string) config('session.driver');

        $usage = [
            'cache (default)' => [$cacheStore, config("cache.stores.{$cacheStore}.driver")],
            'api cache' => [$apiCacheStore, config("cache.stores.{$apiCacheStore}.driver")],
            'queue' => [$queue, config("queue.connections.{$queue}.driver")],
            'session' => [$session, $session],
        ];

        $usesRedis = false;

        foreach ($usage as $label => [$name, $driver]) {
            $onRedis = $driver === 'redis';
            $usesRedis = $usesRedis || $onRedis;

            $this->line(sprintf('%-16s %s%s', $label.':', $name, $onRedis ? ' (redis)' : ''));
        }

        if (! $usesRedis) {
            $this->warn('Cache, queue and session are not on Redis in this environment; the checks below only test connectivity.');
        }
    }
}
