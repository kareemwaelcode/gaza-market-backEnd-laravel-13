<?php

namespace App\Console\Commands;

use App\Jobs\SendSubscriptionReminder;
use App\Models\PlaceSubscription;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'يرسل تذكير واتساب لأصحاب المحلات قبل انتهاء الباقة (مجدول يومياً)';

    public function handle(): int
    {
        $count = 0;

        PlaceSubscription::needingReminder()
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    SendSubscriptionReminder::dispatch($subscription->id);
                    $count++;
                }
            });

        $this->info("Subscription reminders queued: {$count}.");

        return self::SUCCESS;
    }
}