<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Character creation is a two-step flow: /register makes the account, the
 * faction page forges the character. Between those two steps the user is
 * authenticated with no character row, and every game page dereferences one.
 *
 * Guarding here rather than in each component is what makes "no character
 * yet" a single, unmissable state — the faction pick is not skippable by
 * typing a URL.
 */
class EnsureCharacterExists
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->character) {
            return redirect()->route('faction.select');
        }

        return $next($request);
    }
}
