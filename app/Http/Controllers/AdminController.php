<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admins = Admin::orderBy('created_at', 'asc')->paginate(5);
        return response()->view('cms.admin.index', compact('admins'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->view('cms.admin.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
    $validator = validator($request->all(), [
    'full_name' => 'required',
    'email' => 'required',
    'mobile' => 'required',
    'password' => 'required',
    ]);

    if(! $validator->fails()) {
        $admins = new Admin();
        $admins->full_name = $request->input('full_name');
        $admins->email = $request->input('email');
        $admins->mobile = $request->input('mobile');
        $admins->password = Hash::make($request->input('password'));
        $isSaved = $admins->save();

        if ($isSaved) {
            return response()->json(['icon' => 'success' , 'title'=> 'Created is Successfully'] , 200);

        }
        else {
            return response()->json(['icon' => 'error' , 'title' => 'Created is Failed'] , 400);

        }
        
    }
    else {
        return response()->json(['icon' => 'error' ,'title' => $validator->getMessageBag()->first()] , 400);

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
        $admins = Admin::findOrFail($id);
        return response()->view('cms.admin.edit', compact('admins'));  
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
    $validator = validator($request->all(), [
    'full_name' => 'required',
    'email' => 'required',
    'mobile' => 'required',
    ]);

    if(! $validator->fails()) {
    $admins = Admin::FindOrFail($id);
    $admins->full_name = $request->input('full_name');
    $admins->email = $request->input('email');
    $admins->mobile = $request->input('mobile');
    $isUpdated = $admins->save();
    return ['redirect' => route('admin.index')];
    if ($isUpdated) {
        return response()->json(['icon' => 'success' , 'title'=> 'Updated is Successfully'] , 200);

        }
    else {
        return response()->json(['icon' => 'error' , 'title' => 'Updated is Failed'] , 400);

    }
        
    }
    else {
    return response()->json(['icon' => 'error' ,'title' => $validator->getMessageBag()->first()] , 400);

    }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $admins = Admin::destroy($id);
    }
}
