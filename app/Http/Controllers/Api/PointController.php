<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointLevel;
use App\Models\PointTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** نقاط المستخدم الحالي: المجموع، المستوى، المستوى التالي، وسجل العمليات. */
class PointController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    public function show(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $user = $request->user();
        $total = (int) $user->user_points_total;
        $limit = (int) ($request->query('limit') ?: self::PER_PAGE);

        $level = PointLevel::forPoints($total);
        $next = PointLevel::query()
            ->where('point_level_min_points', '>', $total)
            ->orderBy('point_level_min_points')
            ->first();

        $page = PointTransaction::query()
            ->where('user_id', $user->id)
            ->with('pointRule:id,point_rule_key,point_rule_name_en,point_rule_name_ar')
            ->orderByDesc('point_transaction_created_at')
            ->orderByDesc('id')
            ->paginate($limit);

        $history = $page->getCollection()->map(fn (PointTransaction $row) => [
            'id' => $row->id,
            'points' => $row->point_transaction_points,
            'rule' => $row->pointRule ? [
                'key' => $row->pointRule->point_rule_key,
                'name_en' => $row->pointRule->point_rule_name_en,
                'name_ar' => $row->pointRule->point_rule_name_ar,
            ] : null,
            'source_type' => $row->point_transaction_source_type,
            'source_id' => $row->point_transaction_source_id,
            'created_at' => $row->point_transaction_created_at?->toJSON(),
        ])->values();

        return response()->json([
            'data' => [
                'total' => $total,
                'level' => $level ? $this->levelPayload($level) : null,
                'next_level' => $next ? $this->levelPayload($next) + [
                    'points_needed' => $next->point_level_min_points - $total,
                ] : null,
            ],
            'history' => [
                'data' => $history->all(),
                'has_more' => $page->hasMorePages(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function levelPayload(PointLevel $level): array
    {
        return [
            'key' => $level->point_level_key,
            'name_en' => $level->point_level_name_en,
            'name_ar' => $level->point_level_name_ar,
            'min_points' => $level->point_level_min_points,
        ];
    }
}