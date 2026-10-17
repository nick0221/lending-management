<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $requiredRoles = array_values(array_filter(
            array_map(Role::tryFrom(...), $roles),
        ));

        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole(...$requiredRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
