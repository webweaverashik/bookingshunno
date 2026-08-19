<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The flash icon in the admin header.
 *
 * A controller rather than a closure in routes/admin.php because a closure
 * route makes `php artisan route:cache` fail outright, and that command is part
 * of deploying this app.
 *
 * Open to both Admin and Manager. It destroys nothing but derived data — worst
 * case the next few requests are slower while the caches refill.
 */
class CacheController extends Controller
{
    public function __invoke(): JsonResponse
    {
        clearServerCache();

        return response()->json([
            'success' => true,
            'message' => 'Caches cleared.',
        ]);
    }
}
