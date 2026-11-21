<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($roleId)
    {
        $permissions = Permission::all();
        $rolePermissions = Role::findOrFail($roleId)->permissions;

    if (count($rolePermissions) > 0) {
        foreach ($permissions as $permission) {
            $permission->setAttribute('active' , false);
            foreach($rolePermissions as $rolePermission) {
                if($rolePermission->id == $permission->id) {
                    $permission->active = true;
                }

                }
            }
        }
        return response()->view('cms.spatie.role.role-permission' , ['roleId' => $roleId, 'permissions' => $permissions ]);
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
    public function store(Request $request)
    {
        $validator = Validator($request->all(), [
            // 'role_id' => 'required|exists:roles,id',
            // 'permission_id' => 'required|exists:permissions,id',
        ]);

        if (!$validator->fails()) {
            $role = Role::findOrFail($roleId);
            $permission = Permission::findOrFail($request->input('permission_id'));

            if ($role->hasPermissionTo($permission->id)) {
                $role->revokePermissionTo($permission->id);
            } else {
                $role->givePermiisionTo($permission->id);
            }
            return response()->json(['icon' => 'success' , 'title' => 'permission updated successfully'] , 200);

        }

        else{
            return response()->json(['massage' => $validator->getMessageBag()->first()] , 400);
        }
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
