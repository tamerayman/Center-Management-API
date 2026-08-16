<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeMethodOverride
{
    public function handle(Request $request, Closure $next): Response
    {
        if (isset($_POST['_method']) && is_string($_POST['_method'])) {
            $_POST['_method'] = strtoupper(trim($_POST['_method']));
            if (isset($_REQUEST['_method'])) {
                $_REQUEST['_method'] = $_POST['_method'];
            }
            $request->request->set('_method', $_POST['_method']);
        }

        return $next($request);
    }
}

