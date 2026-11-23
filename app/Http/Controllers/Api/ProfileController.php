<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data === []) {
            return response()->json([
                'code' => 'VALIDATION',
                'errors' => ['profile' => ['No fields to update']],
            ], 422);
        }

        $user = $request->user();

        // ربط صريح بالأعمدة: ما في fill() على كل الطلب
        $map = [
            'full_name' => 'user_full_name',
            'preferred_lang' => 'user_preferred_lang',
            'theme' => 'user_theme',
            'area_id' => 'area_id',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $user->{$column} = $data[$input];
            }
        }

        $user->save();

        return response()->json(['data' => $user->refresh()->apiPayload()]);
    }

    public function avatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $old = $user->user_avatar;

        $path = app(ImageProcessor::class)->store($request->file('avatar'), "avatars/{$user->id}", 'public', 'avatar');

        if ($path === false) {
            return response()->json(['code' => 'SERVER'], 500);
        }

        try {
            $user->user_avatar = $path;
            $user->save();
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            report($e);

            return response()->json(['code' => 'SERVER'], 500);
        }

        // حذف الصورة القديمة (الروابط الخارجية https:// ما بنلمسها)
        if ($old !== null && $old !== $path && ! str_starts_with($old, 'https://')) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['data' => $user->refresh()->apiPayload()]);
    }
}