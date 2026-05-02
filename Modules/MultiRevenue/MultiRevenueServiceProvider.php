<?php

namespace Modules\MultiRevenue;

use Illuminate\Support\ServiceProvider;

class MultiRevenueServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->register(RouteServiceProvider::class); }
    public function boot(): void { $this->loadRoutesFrom(__DIR__ . '/routes/api.php'); }
}
