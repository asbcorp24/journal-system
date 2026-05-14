<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserPasswordCipher;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
    public function loginPage()
    {
        if (session('user_id')) {
            return redirect()->route('user.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден',
            ], 422);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь заблокирован',
            ], 422);
        }

        if (!UserPasswordCipher::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный пароль',
            ], 422);
        }

        session([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'user_division_id' => $user->division_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Вход выполнен успешно',
            'redirect' => route('user.dashboard'),
        ]);
    }

    public function logout()
    {
        session()->forget([
            'user_id',
            'user_name',
            'user_email',
            'user_role',
            'user_division_id',
        ]);

        return redirect()->route('user.login');
    }
}
