<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
public function showLogin ($guard) {
    return response()->view('cms.auth.login', compact('guard'));
}
public function login (Request $request) {
$validator = validator($request->all() , [
        'email' => 'required|email',
        'password' => 'required|string',
    ]);
    
    if(! $validator->fails()){
    $credentials =[
        'email' => $request->input('email'),
        'password' => $request->input('password'),

    ];
    if(Auth::guard($request->input('guard'))->attempt($credentials)){
    return response()->json(['icon' => 'success' , 'title' => 'Login is Successfully'] , 200); 
    }
    else{
        return response()->json(['icon' => 'error' , 'title' => 'Login is Failed'] , 400); 

    }
}
else{
    return response()->json(['icon' => 'error' , 'title' => $validator->getMessageBag()->first()] , 400); 
    }
}
public function logout (Request $request) {
    $guard = 'admins';
    Auth::guard($guard)->logout();
    $request->session()->invalidate();
    return redirect()->route('view.login', ['guard' => $guard]);
}

}
