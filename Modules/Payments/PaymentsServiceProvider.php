<?php

namespace Modules\Payments;

use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->register(RouteServiceProvider::class); }
    public function boot(): void { $this->loadRoutesFrom(__DIR__ . '/routes/api.php'); }
}
