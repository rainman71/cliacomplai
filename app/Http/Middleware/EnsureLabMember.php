<?php

namespace App\Http\Middleware;

use App\Models\Lab;
use App\Support\CurrentLab;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes that the signed-in user may access the {lab} in the route, and sets it as the
 * active lab for the request (so the BelongsToLab global scope filters everything to it).
 */
class EnsureLabMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $lab = $request->route('lab');

        if (! $lab instanceof Lab) {
            abort(404);
        }

        abort_unless($request->user() && $request->user()->hasLabAccess($lab), 403);

        app(CurrentLab::class)->set($lab);

        return $next($request);
    }
}
