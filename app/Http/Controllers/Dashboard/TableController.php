<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTableRequest;
use App\Http\Requests\Dashboard\UpdateTableRequest;
use App\Models\DashboardTable;
use App\Models\Place;
use App\Support\PlanOverflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! DashboardTable::supportsPlace($place)) {
            return $this->failure('NOT_FOUND', 404);
        }

        $items = DashboardTable::where('place_id', $place->id)
            ->orderBy('id')
            ->limit((int) config('dashboard_plan.tables.hard_cap'))
            ->get();

        $limit = $this->tableLimit($place->place_plan);
        $kept = PlanOverflow::keptIds(DashboardTable::where('place_id', $place->id), $limit);
        $overCount = $items->filter(fn (DashboardTable $t) => PlanOverflow::isOver($kept, $t->id))->count();

        return response()->json([
            'data' => $items->map(fn (DashboardTable $t) => $this->payload($t, PlanOverflow::isOver($kept, $t->id)))->all(),
            'meta' => [
                'limit' => $limit,
                'used' => DashboardTable::where('place_id', $place->id)->count(),
                // hidden قديم ومحفوظ للتوافق: نفس عدد العناصر فوق حد الباقة بعد الانتهاء.
                'hidden' => $overCount,
                'overLimitCount' => $overCount,
            ],
        ]);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! DashboardTable::supportsPlace($place)) {
            return $this->failure('NOT_FOUND', 404);
        }

        $data = $request->validated();

        $result = DB::transaction(function () use ($place, $data) {
            // قفل المحل يمنع طلبين متزامنين من تجاوز حد الباقة.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $limit = $this->tableLimit($locked->place_plan)
                ?? (int) config('dashboard_plan.tables.hard_cap');

            // الطاولات المحذوفة ناعماً لا تُحسب (SoftDeletes).
            if (DashboardTable::where('place_id', $locked->id)->count() >= $limit) {
                return $this->failure('PLAN_LIMIT_REACHED', 403, [
                    'limit' => $this->tableLimit($locked->place_plan),
                ]);
            }

            $table = new DashboardTable($data);
            $table->place_id = $locked->id;
            $table->save();

            return $table;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['data' => $this->payload($result->refresh())], 201);
    }

    public function update(UpdateTableRequest $request, string $table): JsonResponse
    {
        $found = $this->ownTable($request, $table);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $data = $request->validated();

        if ($data === []) {
            return $this->failure('VALIDATION', 422, [
                'errors' => ['body' => ['No updatable fields were sent.']],
            ]);
        }

        $found->fill($data)->save();

        return response()->json(['data' => $this->payloadFor($found->refresh())]);
    }

    public function destroy(Request $request, string $table): JsonResponse|Response
    {
        $found = $this->ownTable($request, $table);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $found->delete();

        return response()->noContent();
    }

    private function ownPlace(Request $request): Place
    {
        return Place::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function ownTable(Request $request, string $id): ?DashboardTable
    {
        $place = $this->ownPlace($request);

        if (! DashboardTable::supportsPlace($place)) {
            return null;
        }

        return DashboardTable::where('place_id', $place->id)->whereKey((int) $id)->first();
    }

    /** null يعني بلا حد تجاري (الباقة المدفوعة). */
    private function tableLimit(?string $plan): ?int
    {
        return $plan === 'paid'
            ? null
            : (int) config('dashboard_plan.tables.free_limit');
    }

    /** @return array<string, mixed> payload مع حساب علامة "فوق حد الباقة" لطاولة واحدة. */
    private function payloadFor(DashboardTable $t): array
    {
        $kept = PlanOverflow::keptIds(
            DashboardTable::where('place_id', $t->place_id),
            $this->tableLimit($t->place->place_plan),
        );

        return $this->payload($t, PlanOverflow::isOver($kept, $t->id));
    }

    /** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
    private function payload(DashboardTable $t, bool $overLimit = false): array
    {
        return [
            'id' => (string) $t->id,
            'name' => $t->name,
            'seats' => $t->seats,
            'status' => $t->status,
            'overLimit' => $overLimit,
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}