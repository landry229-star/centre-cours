<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs('password.change-required', 'password.update', 'logout')
        ) {
            if ($request->expectsJson()) {
                abort(403, 'Vous devez changer votre mot de passe avant de continuer.');
            }

            return redirect()->route('password.change-required');
        }

        return $next($request);
    }
}
