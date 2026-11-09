<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * صلاحية الوصول للـ admin فقط — نظام التحكم الكامل.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only admins can access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
