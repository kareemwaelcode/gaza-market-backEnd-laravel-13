<?php

namespace App\Jobs;

use App\Models\Place;
use App\Models\PlaceSubscription;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * يرسل لصاحب المحل تذكير واتساب قبل انتهاء الباقة.
 * التكرار يُمنع بـ subscription_reminder_sent_at الذي يُكتب بعد نجاح الإرسال فقط،
 * ومفتاح idempotency ثابت للدفعة. فشل الإرسال لا يمس الباقة.
 */
class SendSubscriptionReminder implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600, 3600];

    public function __construct(public int $subscriptionId)
    {
        $this->afterCommit();
    }

    public function handle(WhatsAppSender $sender): void
    {
        // الشروط تُفحص من جديد: ربما جُدّدت الباقة أو أُلغيت أو أُرسل التذكير قبل تنفيذ المهمة.
        $subscription = PlaceSubscription::needingReminder()
            ->with('place.user')
            ->find($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $place = $subscription->place;
        $user = $place?->user;

        if ($user === null || $user->trashed() || $user->user_status !== 'active') {
            return;
        }

        $to = SendPlaceStatusMessage::toE164((string) $user->user_phone);

        if ($to === null) {
            Log::warning('Subscription reminder skipped: invalid phone number.', [
                'place_id' => $place->id,
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $sender->send(
                $to,
                $this->messageFor($place, $subscription),
                "subscription-reminder-{$subscription->id}"
            );
        } catch (WhatsAppSendException $e) {
            if ($e->retryable) {
                throw $e;
            }

            Log::error('Subscription reminder failed permanently.', [
                'place_id' => $place->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);

            return;
        }

        PlaceSubscription::whereKey($subscription->id)
            ->whereNull('subscription_reminder_sent_at')
            ->update(['subscription_reminder_sent_at' => now(), 'updated_at' => now()]);
    }

    private function messageFor(Place $place, PlaceSubscription $subscription): string
    {
        $ends = $subscription->subscription_ends_at;
        $days = max(1, (int) ceil(now()->diffInSeconds($ends, false) / 86400));
        $date = $ends->copy()->timezone(config('app.display_timezone'))->format('Y-m-d');

        $price = rtrim(rtrim(number_format((float) config('subscription.price'), 2, '.', ''), '0'), '.');
        $currency = (string) config('subscription.currency');
        $currency = $currency === 'ILS' ? 'شيكل' : $currency;
        $months = (int) config('subscription.period_months');

        return "تذكير: تنتهي باقة محلّك \"{$place->place_name}\" على غزة ماركت بعد {$this->daysText($days)} (بتاريخ {$date}).\n"
            ."لتجديدها والاحتفاظ بمزايا الباقة المدفوعة ادخل إلى لوحة التحكم:\n"
            .SendPlaceStatusMessage::loginUrl()."\n"
            ."سعر التجديد: {$price} {$currency} لمدة {$this->monthsText($months)}.";
    }

    private function daysText(int $days): string
    {
        return match (true) {
            $days === 1 => 'يوم واحد',
            $days === 2 => 'يومين',
            default => "{$days} أيام",
        };
    }

    private function monthsText(int $months): string
    {
        return match (true) {
            $months === 1 => 'شهر واحد',
            $months === 2 => 'شهرين',
            $months >= 3 && $months <= 10 => "{$months} أشهر",
            default => "{$months} شهراً",
        };
    }
}