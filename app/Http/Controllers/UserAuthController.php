<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAuthController extends Controller
{
    private const GUARD = 'admins';

    public function showLogin()
    {
        return response()->view('cms.auth.login', ['guard' => self::GUARD]);
    }

    public function login(Request $request)
    {
        $validator = validator($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

        if (Auth::guard(self::GUARD)->attempt($credentials)) {
            $request->session()->regenerate();

            return response()->json(['icon' => 'success', 'title' => 'Login is Successfully'], 200);
        }

        return response()->json(['icon' => 'error', 'title' => 'Login is Failed'], 400);
    }

    public function logout(Request $request)
    {
        Auth::guard(self::GUARD)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('view.login', ['guard' => self::GUARD]);
    }
}