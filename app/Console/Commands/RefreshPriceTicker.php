<?php

namespace App\Console\Commands;

use App\Support\PriceTicker;
use Illuminate\Console\Command;

class RefreshPriceTicker extends Command
{
    protected $signature = 'prices:refresh-ticker {--fresh : امسح الكاش أولاً وابنِ الشريط من جديد}';

    protected $description = 'يبني شريط الأسعار من الداتابيز ويخزّنه بالكاش (مجدول: كل N دقيقة + ليلاً)';

    public function handle(): int
    {
        $ttl = (int) config('prices.ticker.ttl');

        if ($ttl <= 0) {
            $this->warn('Ticker cache is disabled (PRICE_TICKER_TTL=0): nothing to refresh.');

            return self::SUCCESS;
        }

        $interval = max(1, (int) config('prices.ticker.refresh_minutes')) * 60;

        if ($ttl <= $interval) {
            $this->warn("PRICE_TICKER_TTL ({$ttl}s) must be longer than the refresh interval ({$interval}s), otherwise the cache expires between runs.");
        }

        if ($this->option('fresh')) {
            PriceTicker::forget();
        }

        $count = count(PriceTicker::refresh());

        $this->info("Price ticker refreshed: {$count} items.");

        return self::SUCCESS;
    }
}