<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class UserController extends Controller implements HasMiddleware
{
    private const LIST_COLUMNS = [
        'id',
        'user_full_name',
        'user_phone',
        'user_avatar',
        'user_role',
        'user_status',
        'user_preferred_lang',
        'user_theme',
        'user_last_login_at',
        'user_created_at',
        'area_id',
    ];

    private const DETAIL_COLUMNS = [
        'id',
        'user_full_name',
        'user_phone',
        'user_avatar',
        'user_role',
        'user_status',
        'user_preferred_lang',
        'user_theme',
        'user_phone_verified_at',
        'user_last_login_at',
        'user_points_total',
        'user_created_at',
        'updated_at',
        'area_id',
    ];

    public function __construct(private UserAccountService $accounts)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'role' => ['nullable', Rule::in(['user', 'store_owner'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $filters = array_filter($validator->validated());

        $users = User::select(self::LIST_COLUMNS)
            ->with('area:id,area_name_ar')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('user_status', $status))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('user_role', $role))
            ->orderByDesc('id')
            ->paginate(10)
            ->appends($filters)
            ->through(fn (User $user) => $user->append('avatar_url'));

        return response()->json([
            'status' => true,
            'message' => 'Data of Users',
            'data' => $users,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::select(self::DETAIL_COLUMNS)
            ->with(['area:id,area_name_ar', 'place:id,user_id,place_name,place_status'])
            ->findOrFail($id)
            ->append('avatar_url');

        return response()->json([
            'status' => true,
            'message' => 'Data of User',
            'data' => $user,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = validator($request->all(), [
            'user_status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $user = User::findOrFail($id);
        $requested = $request->input('user_status');

        if ($user->user_status !== $requested) {
            $result = $this->accounts->toggleSuspension($user->id);

            if ($result !== $requested) {
                return response()->json([
                    'icon' => 'error',
                    'title' => 'This account status cannot be changed',
                ], 409);
            }
        }

        return response()->json([
            'icon' => 'success',
            'title' => $requested === 'suspended' ? 'User blocked successfully' : 'User unblocked successfully',
            'status' => $requested,
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $this->accounts->delete($user->id);

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }
}