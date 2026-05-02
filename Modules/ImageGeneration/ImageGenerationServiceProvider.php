<?php

namespace Modules\ImageGeneration;

use Illuminate\Support\ServiceProvider;

class ImageGenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
    }
}
