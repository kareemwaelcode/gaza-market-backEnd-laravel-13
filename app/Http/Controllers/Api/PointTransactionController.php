<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * سجل نقاط للقراءة فقط: السجلات بتنكتب من خدمة النقاط، وما بتتعدل ولا بتنحذف من الـ API.
 */
class PointTransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'point_rule_id' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', Rule::in(['product_price', 'price_vote', 'place', 'listing', 'product'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $filters = array_filter($validator->validated(), fn ($value) => $value !== null);

        $transactions = PointTransaction::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'pointRule:id,point_rule_key,point_rule_name_en,point_rule_name_ar',
            ])
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['point_rule_id'] ?? null, fn ($q, $id) => $q->where('point_rule_id', $id))
            ->when($filters['source_type'] ?? null, fn ($q, $type) => $q->where('point_transaction_source_type', $type))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->appends($filters);

        return response()->json([
            'status' => true,
            'message' => 'Data of Point Transactions',
            'data' => $transactions,
        ], 200);
    }
}