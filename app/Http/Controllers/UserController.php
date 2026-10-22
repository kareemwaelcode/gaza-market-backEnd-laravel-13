<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccountService;

class UserController extends Controller
{
    public function __construct(private UserAccountService $accounts)
    {
    }

    public function index()
    {
        $users = User::select([
                'id',
                'user_full_name',
                'user_phone',
                'user_role',
                'user_status',
                'user_last_login_at',
                'user_created_at',
                'area_id',
            ])
            ->with('area:id,area_name_en')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->view('cms.user.index', compact('users'));
    }

    public function toggleStatus(string $id)
    {
        $status = $this->accounts->toggleSuspension((int) $id);

        if ($status === null) {
            return response()->json(['icon' => 'error', 'title' => 'This account status cannot be changed'], 400);
        }

        $title = $status === 'suspended' ? 'User blocked successfully' : 'User unblocked successfully';

        return response()->json(['icon' => 'success', 'title' => $title, 'status' => $status], 200);
    }

    public function destroy(string $id)
    {
        $this->accounts->delete((int) $id);

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }
}