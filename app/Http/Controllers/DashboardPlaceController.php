<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDashboardPlaceLogoRequest;
use App\Http\Requests\UpdateDashboardPlaceRequest;
use App\Models\Place;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * لوحة صاحب المحل (المرحلة أ): قراءة وتعديل بيانات محله.
 * المحل يُحدد دائماً من الجلسة (user_id)، ولا يُقبل أي معرّف محل من الطلب.
 * المسارات محمية بـ store.owner (مستخدم نشط + دور store_owner + محل معتمد).
 */
class DashboardPlaceController extends Controller
{
/** حقل الطلب => عمود القاعدة. هذه هي الحقول الوحيدة التي يعدّلها المالك. */
private const COLUMNS = [
    'name' => 'place_name',
    'address' => 'place_address',
    'phone' => 'place_phone',
    'whatsapp' => 'place_whatsapp',
    'is_open' => 'place_is_open',
];

public function show(Request $request): JsonResponse
{
    return response()->json(['data' => $this->payload($this->ownPlace($request))]);
}

public function update(UpdateDashboardPlaceRequest $request): JsonResponse
{
    $place = $this->ownPlace($request);
    $data = $request->validated();

    if ($data === []) {
        return $this->failure('VALIDATION', 422, [
            'errors' => ['body' => ['No updatable fields were sent.']],
        ]);
    }

    $taken = $this->takenField($data['phone'] ?? null, $data['whatsapp'] ?? null, $place->id);

    if ($taken !== null) {
        return $this->failure('NUMBER_TAKEN', 409, ['field' => $taken]);
    }

    foreach (self::COLUMNS as $field => $column) {
        if (array_key_exists($field, $data)) {
            $place->{$column} = $field === 'is_open' ? (bool) $data[$field] : $data[$field];
        }
    }

    $changed = array_keys($place->getDirty());

    try {
        $place->save();
    } catch (UniqueConstraintViolationException) {
        // سباق مع طلب آخر أخذ نفس الرقم بين الفحص والحفظ.
        $taken = $this->takenField($data['phone'] ?? null, $data['whatsapp'] ?? null, $place->id);

        return $this->failure('NUMBER_TAKEN', 409, $taken !== null ? ['field' => $taken] : []);
    }

    if ($changed !== []) {
        // أسماء الحقول فقط، بدون القيم (أرقام هواتف).
        Log::info('Owner updated place.', [
            'place_id' => $place->id,
            'user_id' => $request->user()->id,
            'fields' => $changed,
        ]);
    }

    return response()->json(['data' => $this->payload($place->refresh())]);
}

public function logo(UpdateDashboardPlaceLogoRequest $request): JsonResponse
{
    $place = $this->ownPlace($request);
    $oldLogo = $place->place_logo;

    $path = $request->file('logo')->store("places/{$place->id}", 'public');

    if ($path === false) {
        return $this->failure('SERVER', 500);
    }

    try {
        $place->place_logo = $path;
        $place->save();
    } catch (Throwable $e) {
        Storage::disk('public')->delete($path);
        report($e);

        return $this->failure('SERVER', 500);
    }

    if ($oldLogo !== null && $oldLogo !== $path) {
        Storage::disk('public')->delete($oldLogo);
    }

    return response()->json(['data' => $this->payload($place->refresh())]);
}

private function ownPlace(Request $request): Place
{
    return Place::with('storeCategory:id,store_category_name_en')
        ->where('user_id', $request->user()->id)
        ->firstOrFail();
}

private function takenField(?string $phone, ?string $whatsapp, int $ignoreId): ?string
{
    $exists = fn (string $column, string $value) => Place::where($column, $value)
        ->where('id', '!=', $ignoreId)
        ->exists();

    if ($phone !== null && $exists('place_phone', $phone)) {
        return 'phone';
    }

    if ($whatsapp !== null && $exists('place_whatsapp', $whatsapp)) {
        return 'whatsapp';
    }

    return null;
}

/** @return array<string, mixed> */
private function payload(Place $place): array
{
    return [
        'id' => $place->id,
        'status' => $place->place_status,
        'name' => $place->place_name,
        'type' => $place->place_type,
        'sub_category' => $place->storeCategory?->store_category_name_en,
        'address' => $place->place_address,
        'phone' => $place->place_phone,
        'whatsapp' => $place->place_whatsapp,
        'logo_url' => $place->logo_url,
        'is_open' => (bool) $place->place_is_open,
        'plan' => $place->place_plan,
        'activated' => $place->place_activated_at !== null,
    ];
}

private function failure(string $code, int $status, array $extra = []): JsonResponse
{
    return response()->json(array_merge(['code' => $code], $extra), $status);
}
}