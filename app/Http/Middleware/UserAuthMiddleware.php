<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class UserAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('user_id')) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходима авторизация',
                ], 401);
            }

            return redirect()->route('user.login');
        }

        $user = User::query()->find(session('user_id'));
        if ($user && $user->is_active) {
            session([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'user_division_id' => $user->division_id,
                'can_edit_directory_templates' => $user->can_edit_directory_templates,
            ]);
        }

        return $next($request);
    }
}
