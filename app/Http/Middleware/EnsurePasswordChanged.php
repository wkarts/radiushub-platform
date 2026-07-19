<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs('profile.*')
            && ! $request->routeIs('logout')) {
            return redirect()->route('profile.edit')->with('warning', 'Defina uma nova senha antes de continuar.');
        }

        return $next($request);
    }
}
