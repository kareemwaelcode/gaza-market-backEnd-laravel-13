<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| شريط الأسعار (config/prices.php)
|--------------------------------------------------------------------------
| الزائر بيقرأ من الكاش بس؛ الداتابيز بتنضرب من هون. قيم .env الغلط بترجع للافتراضي
| بدل ما تكسر كل أوامر artisan. بالإنتاج لازم cron: * * * * * php artisan schedule:run
*/
$tickerMinutes = (int) config('prices.ticker.refresh_minutes');
$tickerMinutes = ($tickerMinutes >= 1 && $tickerMinutes <= 59) ? $tickerMinutes : 10;

$tickerNightlyAt = (string) config('prices.ticker.nightly_at');
$tickerNightlyAt = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tickerNightlyAt) ? $tickerNightlyAt : '03:05';

$tickerTimezone = (string) config('prices.ticker.timezone');
$tickerTimezone = in_array($tickerTimezone, DateTimeZone::listIdentifiers(), true) ? $tickerTimezone : 'Asia/Gaza';

Schedule::command('prices:refresh-ticker')
    ->cron("*/{$tickerMinutes} * * * *")
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('prices:refresh-ticker --fresh')
    ->dailyAt($tickerNightlyAt)
    ->timezone($tickerTimezone)
    ->withoutOverlapping(5)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| تذكير انتهاء الباقة (config/subscription.php => reminder)
|--------------------------------------------------------------------------
| مرة باليوم. التكرار ممنوع بعمود subscription_reminder_sent_at، والإرسال عبر الطابور
| (لازم queue:work شغّال). قيم .env الغلط بترجع للافتراضي.
*/
$reminderAt = (string) config('subscription.reminder.at');
$reminderAt = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminderAt) ? $reminderAt : '10:00';

$reminderTimezone = (string) config('subscription.reminder.timezone');
$reminderTimezone = in_array($reminderTimezone, DateTimeZone::listIdentifiers(), true) ? $reminderTimezone : 'Asia/Gaza';

Schedule::command('subscriptions:send-reminders')
    ->dailyAt($reminderAt)
    ->timezone($reminderTimezone)
    ->withoutOverlapping(5)
    ->onOneServer();