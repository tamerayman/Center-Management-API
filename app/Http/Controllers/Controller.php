<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * عدد عناصر الصفحة للـ pagination — بحد أقصى 100.
     */
    protected function perPage(Request $request, int $default = 10): int
    {
        return min(max((int) $request->query('per_page', $default), 1), 100);
    }
}
