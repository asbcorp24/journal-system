<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function loginPage()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $envLogin = config('admin.superadmin_login');
        $envPassword = config('admin.superadmin_password');

        if (
            $request->login === $envLogin &&
            hash_equals($envPassword, $request->password)
        ) {
            session([
                'is_superadmin' => true,
                'superadmin_login' => $request->login,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Вход выполнен успешно',
                'redirect' => route('admin.users.index'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Неверный логин или пароль',
        ], 422);
    }

    public function logout()
    {
        session()->forget([
            'is_superadmin',
            'superadmin_login',
        ]);

        return redirect()->route('admin.login');
    }
}
