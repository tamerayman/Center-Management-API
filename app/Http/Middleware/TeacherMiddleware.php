<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherMiddleware
{
    /**
     * المحمول للـ teacher فقط — والـ admin له صلاحيات تجاوز في كل مكان.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->user()->role, ['teacher', 'admin'])) {
            return response()->json([
                'message' => 'Only teachers can access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
