<?php

namespace Modules\Admin;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'zero.trust', 'role:admin'])
            ->prefix('api/v1/admin')
            ->group(__DIR__ . '/routes/api.php');
    }
}
