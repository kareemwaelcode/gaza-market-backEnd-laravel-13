<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /** لازم يطابق مفتاح الـ guard في config('auth.guards') وقيمة $guard_name في موديل Admin */
    private const GUARD = 'admins';

    public function index()
    {
        $admins = Admin::with('roles:id,name')->orderBy('created_at', 'asc')->paginate(5);
        return response()->view('cms.admin.index', compact('admins'));
    }

    public function create()
    {
        $roles = Role::where('guard_name', self::GUARD)->orderBy('name')->get();
        return response()->view('cms.admin.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'full_name' => 'required',
            'email'     => 'required',
            'mobile'    => 'required',
            'password'  => 'required',
            'role_id'   => ['required', Rule::exists('roles', 'id')->where('guard_name', self::GUARD)],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        DB::transaction(function () use ($request) {
            $admin = new Admin();
            $admin->full_name = $request->input('full_name');
            $admin->email = $request->input('email');
            $admin->mobile = $request->input('mobile');
            $admin->password = Hash::make($request->input('password'));
            $admin->save();

            $admin->syncRoles([(int) $request->input('role_id')]);
        });

        return response()->json(['icon' => 'success', 'title' => 'Created is Successfully'], 200);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        $admins = Admin::with('roles:id,name')->findOrFail($id);
        $roles = Role::where('guard_name', self::GUARD)->orderBy('name')->get();
        $currentRoleId = $admins->roles->first()?->id;

        return response()->view('cms.admin.edit', compact('admins', 'roles', 'currentRoleId'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'full_name' => 'required',
            'email'     => 'required',
            'mobile'    => 'required',
            'role_id'   => ['required', Rule::exists('roles', 'id')->where('guard_name', self::GUARD)],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $admin = Admin::findOrFail($id);

        DB::transaction(function () use ($request, $admin) {
            $admin->full_name = $request->input('full_name');
            $admin->email = $request->input('email');
            $admin->mobile = $request->input('mobile');
            $admin->save();

            $admin->syncRoles([(int) $request->input('role_id')]);
        });

        // storeRoute() في crud.js يقرأ response.data.redirect
        return ['redirect' => route('admin.index')];
    }

    public function destroy(string $id)
    {
        Admin::destroy($id);
    }
}