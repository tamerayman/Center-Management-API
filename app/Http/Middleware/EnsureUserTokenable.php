<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يضمن أن التوكن على مسارات users (auth:sanctum) صادر عن User
 * وليس عن Guardian — عكس EnsureGuardian.
 */
class EnsureUserTokenable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            return response()->json([
                'message' => 'This endpoint is for registered users only.',
            ], 403);
        }

        return $next($request);
    }
}
