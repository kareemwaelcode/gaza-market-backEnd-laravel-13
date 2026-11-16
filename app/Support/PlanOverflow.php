<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * يحدد العناصر الزايدة عن حد الباقة بعد انتهاء الاشتراك، دون حذف ودون عمود جديد.
 *
 * القاعدة: أقدم N عنصر (حسب id) تبقى ضمن الباقة، والباقي "فوق حد الباقة".
 * الحساب يتم عند القراءة، فالتجديد يرجّع كل شي تلقائياً لأن place_plan يصير paid.
 */
final class PlanOverflow
{
    /**
     * معرّفات العناصر التي تبقى ضمن الباقة، أو null إذا لا يوجد حد (كلها ضمن الباقة).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query  استعلام عناصر محل واحد ونوع واحد
     * @return array<int, int>|null
     */
    public static function keptIds(Builder $query, ?int $limit): ?array
    {
        if ($limit === null) {
            return null;
        }

        return $query->clone()
            ->reorder('id')
            ->limit(max($limit, 0))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>|null  $keptIds  ناتج keptIds()
     */
    public static function isOver(?array $keptIds, int $id): bool
    {
        return $keptIds !== null && ! in_array($id, $keptIds, true);
    }
}