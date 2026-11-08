<?php

namespace App\Jobs;

use App\Models\Place;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * يرسل لصاحب المحل رسالة واتساب بعد قبول أو رفض محله.
 * يُطلق بعد الـ commit، وفشل الإرسال لا يلغي القبول أو الرفض.
 */
class SendPlaceStatusMessage implements ShouldQueue
{
use InteractsWithQueue, Queueable;

public int $tries = 5;

/** @var array<int, int> */
public array $backoff = [30, 120, 600, 3600];

public function __construct(
public int $placeId,
public string $status
) {
$this->afterCommit();
}

public function handle(WhatsAppSender $sender): void
{
// التأكد من أن الحالة المطلوبة مدعومة.
if (! in_array($this->status, [
    Place::STATUS_APPROVED,
    Place::STATUS_REJECTED,
], true)) {
    Log::warning('Place status message skipped: unsupported status.', [
        'place_id' => $this->placeId,
        'status' => $this->status,
    ]);

    return;
}

// جلب المحل مع المستخدم المرتبط به.
$place = Place::with('user')->find($this->placeId);

// المحل غير موجود أو تغيرت حالته قبل تنفيذ المهمة.
if ($place === null || $place->place_status !== $this->status) {
    return;
}

$user = $place->user;

// التأكد من أن المستخدم موجود ونشط وغير محذوف.
if (
    $user === null ||
    $user->trashed() ||
    $user->user_status !== 'active'
) {
    return;
}

// تحويل رقم الهاتف إلى الصيغة الدولية.
$to = self::toE164((string) $user->user_phone);

if ($to === null) {
    Log::warning('Place status message skipped: invalid phone number.', [
        'place_id' => $place->id,
    ]);

    return;
}

try {
    $sender->send(
        $to,
        $this->messageFor($place),
        $this->idempotencyKey($place)
    );
} catch (WhatsAppSendException $e) {
    // إعادة المحاولة عند حدوث خطأ مؤقت.
    if ($e->retryable) {
        throw $e;
    }

    // تسجيل الأخطاء التي لا يمكن إعادة المحاولة فيها.
    Log::error('Place status message failed permanently.', [
        'place_id' => $place->id,
        'status' => $this->status,
        'error' => $e->getMessage(),
    ]);

    $this->fail($e);
}
}

/**
 * إنشاء رابط تسجيل الدخول إلى لوحة صاحب المحل.
 */
public static function loginUrl(): string
{
return rtrim((string) config('owner_dashboard.base_url'), '/')
    . '/'
    . ltrim((string) config('owner_dashboard.login_path'), '/');
}


public static function toE164(string $phone): ?string
{
// الاحتفاظ بالأرقام فقط.
$digits = preg_replace('/\D+/', '', $phone) ?? '';

// تحويل 00 في بداية الرقم إلى صيغة دولية.
if (str_starts_with($digits, '00')) {
    $digits = substr($digits, 2);
}

// تحويل الرقم المحلي إلى مفتاح فلسطين 970.
if (str_starts_with($digits, '0')) {
    $digits = '970' . substr($digits, 1);
}

// السماح بمفتاح فلسطين 970 أو 972 فقط.
if (! preg_match('/^(970|972)\d{8,10}$/', $digits)) {
    return null;
}

return '+' . $digits;
}

/**
 * إنشاء نص الرسالة حسب حالة المحل.
 */
private function messageFor(Place $place): string
{
if ($this->status === Place::STATUS_APPROVED) {
    return "مبروك! تم قبول محلّك \"{$place->place_name}\" على غزة ماركت 🎉\n"
        . "ادخل الى لوحة التحكم من خلال الرابط:\n"
        . self::loginUrl() . "\n"
        . 'سنرسل لك كود تحقق على هذا الرقم عند الدخول.';
}

return "نعتذر، لم تتم الموافقة على طلب تسجيل محلّك \"{$place->place_name}\" على غزة ماركت.\n"
    . 'يمكنك تعديل بياناتك وإعادة إرسال الطلب من حسابك على المنصة.';
}

/**
 * إنشاء مفتاح ثابت عبر إعادة المحاولات.
 */
private function idempotencyKey(Place $place): string
{
$stamp = $this->status === Place::STATUS_APPROVED
    ? $place->place_approved_at?->timestamp
    : $place->updated_at?->timestamp;

return "place-{$place->id}-{$this->status}-{$stamp}";
}
}