<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * أسماء الـ guards المسموحة للرولات. لازم تطابق مفاتيح config('auth.guards'):
     * Spatie يستخدمها لإيجاد موديل المستخدم عند حذف الرول (users()->detach()).
     */
    private const GUARDS = ['admins', 'users'];

    public function index()
    {
        $roles = Role::withCount('permissions')->orderBy('id', 'desc')->paginate(10);
        return response()->view('cms.spatie.role.index', compact('roles'));
    }

    public function create()
    {
        return response()->view('cms.spatie.role.create');
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'guard_name' => ['required', Rule::in(self::GUARDS)],
            'name'       => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', $request->input('guard_name'))],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $role = new Role();
        $role->guard_name = $request->input('guard_name');
        $role->name = $request->input('name');
        $isSaved = $role->save();

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
        $roles = Role::findOrFail($id);
        return response()->view('cms.spatie.role.edit', compact('roles'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'guard_name' => ['required', Rule::in(self::GUARDS)],
            'name'       => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', $request->input('guard_name'))->ignore($id)],
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $role = Role::findOrFail($id);
        $role->guard_name = $request->input('guard_name');
        $role->name = $request->input('name');
        $isUpdated = $role->save();

        return response()->json(
            [
                'icon'     => $isUpdated ? 'success' : 'error',
                'title'    => $isUpdated ? 'Updated successfully' : 'Update failed',
                'redirect' => route('roles.index'),
            ],
            $isUpdated ? 200 : 400
        );
    }

    public function destroy(string $id)
    {
        $isDeleted = Role::findOrFail($id)->delete();

        return response()->json(
            ['icon' => $isDeleted ? 'success' : 'error', 'title' => $isDeleted ? 'Deleted successfully' : 'Delete failed'],
            $isDeleted ? 200 : 400
        );
    }
}