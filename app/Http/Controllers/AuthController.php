<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpThrottledException;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\Place;
use App\Models\User;
use App\Services\OtpService;
use App\Services\UserAccountService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل ودخول بالواتساب + كود تفعيل.
 * الأغراض: register (حساب جديد) و login (المنصة) و dashboard (لوحة صاحب المحل).
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly UserAccountService $accounts,
    ) {}

    /**
     * المستخدم الحالي، أو 401 للزائر.
     * يُستخدم أيضاً لجلب XSRF cookie قبل أول POST من الفرونت (الـ cookie تُرسل مع الرد حتى لو كان 401).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->failure('UNAUTHENTICATED', 401);
        }

        return response()->json(['data' => $this->payload($user)]);
    }

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $purpose = $request->string('purpose')->toString();

        if ($purpose === 'register') {
            // يشمل الحسابات المحذوفة: رقم المحذوف يبقى محجوزاً.
            if (User::withTrashed()->where('user_phone', $phone)->exists()) {
                return $this->failure('ACCOUNT_EXISTS', 409);
            }

            $eligible = true;
            $meta = ['full_name' => $request->string('full_name')->toString()];
        } else {
            // رقم غير مؤهل → نفس الرد بالضبط بدون إرسال (لا نكشف وجود الحساب ولا نستهلك الباقة).
            $eligible = $this->eligibleUser($phone, $purpose) !== null;
            $meta = [];
        }

        try {
            $this->otp->request($phone, $purpose, $eligible, $meta);
        } catch (OtpThrottledException $e) {
            return $this->failure('THROTTLED', 429, [
                'reason' => $e->reason,
                'retry_after' => $e->retryAfter,
            ])->header('Retry-After', (string) $e->retryAfter);
        }

        return response()->json(['data' => [
            'expires_in' => (int) config('otp.ttl'),
            'resend_after' => (int) config('otp.resend_cooldown'),
        ]], 202);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $purpose = $request->string('purpose')->toString();

        try {
            $meta = $this->otp->verify($phone, $purpose, $request->string('code')->toString());
        } catch (OtpThrottledException) {
            return $this->failure('THROTTLED', 429);
        }

        if ($meta === null) {
            return $this->failure('INVALID_CODE', 422);
        }

        if ($purpose === 'register') {
            $user = $this->register($phone, (string) ($meta['full_name'] ?? ''));

            if ($user === null) {
                return $this->failure('ACCOUNT_EXISTS', 409);
            }
        } else {
            $user = User::where('user_phone', $phone)->where('user_status', 'active')->first();

            if ($user === null) {
                return $this->failure('INVALID_CODE', 422);
            }

            if ($purpose === 'dashboard' && ! $user->canUseOwnerDashboard()) {
                return $this->failure('FORBIDDEN', 403);
            }
        }

        $user->user_phone_verified_at ??= now();
        $user->user_last_login_at = now();
        $user->save();

        if ($purpose === 'dashboard') {
            // أول دخول ناجح للوحة = تفعيل المحل.
            Place::where('user_id', $user->id)->whereNull('place_activated_at')->update(['place_activated_at' => now()]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $this->payload($user)], $purpose === 'register' ? 201 : 200);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    private function eligibleUser(string $phone, string $purpose): ?User
    {
        $user = User::where('user_phone', $phone)->where('user_status', 'active')->first();

        if ($user === null) {
            return null;
        }

        return $purpose === 'dashboard' && ! $user->canUseOwnerDashboard() ? null : $user;
    }

    private function register(string $phone, string $fullName): ?User
    {
        try {
            return DB::transaction(fn () => $this->accounts->create([
                'user_full_name' => $fullName,
                'user_phone' => $phone,
                'user_role' => 'user',
                'phone_verified' => true,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->user_full_name,
            'phone' => $user->user_phone,
            'role' => $user->user_role,
            'preferred_lang' => $user->user_preferred_lang,
            'theme' => $user->user_theme,
            'area_id' => $user->area_id,
            'avatar_url' => $user->avatar_url,
            'points_total' => $user->user_points_total,
        ];
    }

    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}