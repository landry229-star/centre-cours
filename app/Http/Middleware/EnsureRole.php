<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage dans les routes : ->middleware('role:admin') ou ->middleware('role:admin,teacher')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasRole(...$roles),
            403,
            "Vous n'avez pas accès à cette section."
        );

        return $next($request);
    }
}
