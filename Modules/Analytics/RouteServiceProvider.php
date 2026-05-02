<?php

namespace Modules\Analytics;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'zero.trust'])
            ->prefix('api/v1/analytics')
            ->group(__DIR__ . '/routes/api.php');
    }
}
