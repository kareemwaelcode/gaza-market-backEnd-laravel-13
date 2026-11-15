<?php

namespace App\Services;

use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * منح النقاط حسب قواعد جدول point_rules.
 *
 * - القاعدة غير موجودة أو غير مفعّلة أو نقاطها 0  => ما في منح (بدون خطأ).
 * - نفس (مستخدم، قاعدة، مصدر) ما بينمنح مرتين: القيد الفريد pt_user_rule_source_unique هو الحماية الفعلية.
 * - الحد اليومي بيُفحص بعد قفل صف المستخدم، فطلبين متزامنين لنفس المستخدم ما بيتجاوزوا الحد.
 * - مجموع النقاط (user_points_total) unsigned: النقاط السالبة ما بتنزّله تحت الصفر.
 *
 * لازم تنستدعى داخل transaction الميزة نفسها (إضافة سعر مثلاً) حتى يتراجع المنح مع التراجع.
 */
class PointService
{
    public function award(int $userId, string $ruleKey, string $sourceType, int $sourceId): ?PointTransaction
    {
        $rule = PointRule::query()
            ->where('point_rule_key', $ruleKey)
            ->where('point_rule_is_active', true)
            ->first();

        if ($rule === null || $rule->point_rule_points === 0) {
            return null;
        }

        return DB::transaction(function () use ($userId, $rule, $sourceType, $sourceId) {
            // قفل صف المستخدم: بيرتّب المنح المتزامن لنفس المستخدم
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user === null || $user->user_status !== 'active') {
                return null;
            }

            if ($this->dailyLimitReached($userId, $rule)) {
                return null;
            }

            try {
                // transaction متداخلة (savepoint): فشل القيد الفريد ما بيخرّب المعاملة الخارجية
                $transaction = DB::transaction(fn () => PointTransaction::create([
                    'user_id' => $userId,
                    'point_rule_id' => $rule->id,
                    'point_transaction_points' => $rule->point_rule_points,
                    'point_transaction_source_type' => $sourceType,
                    'point_transaction_source_id' => $sourceId,
                ]));
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            $user->user_points_total = max(0, (int) $user->user_points_total + $rule->point_rule_points);
            $user->save();

            return $transaction;
        });
    }

    private function dailyLimitReached(int $userId, PointRule $rule): bool
    {
        if ($rule->point_rule_daily_limit === null) {
            return false;
        }

        $startOfDay = now(config('points.timezone'))
            ->startOfDay()
            ->setTimezone(config('app.timezone'));

        return PointTransaction::query()
            ->where('user_id', $userId)
            ->where('point_rule_id', $rule->id)
            ->where('point_transaction_created_at', '>=', $startOfDay)
            ->count() >= $rule->point_rule_daily_limit;
    }
}