<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAccountService
{
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