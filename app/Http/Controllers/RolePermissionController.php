<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Role $role)
    {
        $permissions = Permission::where('guard_name', $role->guard_name)
            ->orderBy('name')
            ->get();

        $assignedIds = $role->permissions()->pluck('permissions.id')->all();

        return response()->view('cms.spatie.role.role-permission', compact('role', 'permissions', 'assignedIds'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Role $role)
    {
        $validator = validator($request->all(), [
            'permission_id' => [
                'required',
                'integer',
                Rule::exists('permissions', 'id')->where('guard_name', $role->guard_name),
            ],
            'active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $permission = Permission::findOrFail($request->input('permission_id'));
        $active = $request->boolean('active');

        $active ? $role->givePermissionTo($permission) : $role->revokePermissionTo($permission);

        return response()->json(['icon' => 'success', 'title' => 'permission updated successfully', 'active' => $active], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}