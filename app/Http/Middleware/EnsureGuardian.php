<?php

namespace App\Http\Middleware;

use App\Models\Guardian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يضمن أن التوكن على مسارات /guardian/* صادر عن Guardian وليس User.
 * (auth:guardian وحده بيكفي للتسجيل، لكن Sanctum بيرجع أي tokenable — الفحص ده بيمنع التلوث)
 */
class EnsureGuardian
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('guardian') instanceof Guardian) {
            return response()->json([
                'message' => 'This endpoint is for guardians only.',
            ], 403);
        }

        return $next($request);
    }
}
