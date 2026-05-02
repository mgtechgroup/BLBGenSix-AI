<?php

namespace Modules\Verification;

use Illuminate\Support\ServiceProvider;

class VerificationServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->register(RouteServiceProvider::class); }
    public function boot(): void { $this->loadRoutesFrom(__DIR__ . '/routes/api.php'); }
}
