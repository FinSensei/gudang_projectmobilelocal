<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Batasi akses berdasarkan role.
     * Pakai: ->middleware('role:admin') atau ->middleware('role:admin,pekerja')
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!empty($roles) && !in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}