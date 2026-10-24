<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAccountService
{
    /**
     * Create a user account. Validation is done before (Form Request).
     * Missing optional values fall back to the database defaults
     * (status = active, language = ar, theme = light).
     * If 'phone_verified' is truthy, the phone is marked as verified now.
     *
     * The password is hashed by the 'hashed' cast on the User model.
     * A concurrent request with the same phone is stopped by the unique index
     * (UniqueConstraintViolationException), which the caller handles.
     */
    public function create(array $data): User
    {
        $attributes = [
            'user_full_name' => $data['user_full_name'],
            'user_phone' => $data['user_phone'],
            'user_role' => $data['user_role'],
            'area_id' => $data['area_id'] ?? null,
        ];

        foreach (['user_password', 'user_status', 'user_preferred_lang', 'user_theme'] as $optional) {
            if (! empty($data[$optional])) {
                $attributes[$optional] = $data[$optional];
            }
        }

        $user = new User($attributes);

        // user_phone_verified_at is not mass-assignable, so it is set explicitly.
        if (! empty($data['phone_verified'])) {
            $user->user_phone_verified_at = now();
        }

        $user->save();

        return $user->refresh();
    }

    /**
     * Block (suspend) an active user, or unblock a suspended one.
     * Returns the new status, or null if the user is in a state that cannot be toggled.
     */
    public function toggleSuspension(int $id): ?string
    {
        return DB::transaction(function () use ($id) {
            $user = User::lockForUpdate()->findOrFail($id);

            if ($user->user_status === 'active') {
                $user->user_status = 'suspended';
                $user->save();
                $this->forgetSessions($user->id);

                return 'suspended';
            }

            if ($user->user_status === 'suspended') {
                $user->user_status = 'active';
                $user->save();

                return 'active';
            }

            return null;
        });
    }

    /**
     * Soft delete: the row stays in the database (the phone number stays reserved
     * by the unique index), and every session of the user is removed.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $user = User::lockForUpdate()->findOrFail($id);

            $user->user_status = 'deleted';
            $user->save();
            $user->delete();

            $this->forgetSessions($user->id);
        });
    }

    private function forgetSessions(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
    }
}