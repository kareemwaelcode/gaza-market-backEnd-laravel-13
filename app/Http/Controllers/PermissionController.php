<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    private const GUARDS = ['admins', 'users'];

    public function index()
    {
        $permissions = Permission::orderBy('id', 'desc')->paginate(10);
        return response()->view('cms.spatie.permission.index', compact('permissions'));
    }

    public function create()
    {
        return response()->view('cms.spatie.permission.create');
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'guard_name' => ['required', Rule::in(self::GUARDS)],
            'name'       => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $permission = new Permission();
        $permission->guard_name = $request->input('guard_name');
        $permission->name = $request->input('name');
        $isSaved = $permission->save();

        return response()->json(
            ['icon' => $isSaved ? 'success' : 'error', 'title' => $isSaved ? 'Created successfully' : 'Create failed'],
            $isSaved ? 200 : 400
        );
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        $permissions = Permission::findOrFail($id);
        return response()->view('cms.spatie.permission.edit', compact('permissions'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'guard_name' => ['required', Rule::in(self::GUARDS)],
            'name'       => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $permission = Permission::findOrFail($id);
        $permission->guard_name = $request->input('guard_name');
        $permission->name = $request->input('name');
        $isUpdated = $permission->save();

        return response()->json(
            [
                'icon'     => $isUpdated ? 'success' : 'error',
                'title'    => $isUpdated ? 'Updated successfully' : 'Update failed',
                'redirect' => route('permissions.index'),
            ],
            $isUpdated ? 200 : 400
        );
    }

    public function destroy(string $id)
    {
        $isDeleted = Permission::findOrFail($id)->delete();

        return response()->json(
            ['icon' => $isDeleted ? 'success' : 'error', 'title' => $isDeleted ? 'Deleted successfully' : 'Delete failed'],
            $isDeleted ? 200 : 400
        );
    }
}