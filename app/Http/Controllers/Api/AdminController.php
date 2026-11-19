<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller implements HasMiddleware
{
    // هاش وهمي: نفس زمن التحقق سواء الإيميل موجود أو لا
    private const DUMMY_HASH = '$2y$12$usesomesillystringforsalt.0000000000000000000000000000';

    public static function middleware(): array
    {
        return [
            new Middleware('throttle:admin-login', only: ['login']),
            new Middleware('auth:admins-api', except: ['login']),
        ];
    }

    public function login(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $admin = Admin::where('email', $request->input('email'))->first();
        $passwordOk = Hash::check($request->input('password'), $admin?->password ?? self::DUMMY_HASH);

        if (! $admin || ! $passwordOk) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $admin->createToken('admin-api');

        return response()->json([
            'status' => true,
            'message' => 'Logged in successfully',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->accessToken,
                'admin' => $admin,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        // يلغي كل توكنات هذا الأدمن (تسجيل خروج من كل الأجهزة)
        $request->user('admins-api')->tokens()->update(['revoked' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Data of current Admin',
            'data' => $request->user('admins-api'),
        ], 200);
    }

    public function index(): JsonResponse
    {
        $admins = Admin::orderBy('id')->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Data of Admins',
            'data' => $admins,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $admin = new Admin();
        $admin->full_name = $request->input('full_name');
        $admin->email = $request->input('email');
        $admin->mobile = $request->input('mobile');
        $admin->password = Hash::make($request->input('password'));
        $admin->save();

        return response()->json([
            'status' => true,
            'message' => 'Created Successfully',
            'data' => $admin,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Admin',
            'data' => $admin,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        $validator = validator($request->all(), [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $admin->full_name = $request->input('full_name');
        $admin->email = $request->input('email');
        $admin->mobile = $request->input('mobile');
        $admin->save();

        return response()->json([
            'status' => true,
            'message' => 'Updated Successfully',
            'data' => $admin,
        ], 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        if ($admin->id === $request->user('admins-api')->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account',
            ], 409);
        }

        $admin->tokens()->update(['revoked' => true]);
        $admin->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}