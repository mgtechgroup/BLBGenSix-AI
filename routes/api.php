<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['throttle:api'])->group(function () {
    
    // Public endpoints (no auth required)
    Route::get('/health', fn() => response()->json(['status' => 'healthy', 'timestamp' => now()->toIso8601String()]));
    Route::get('/plans', [\Modules\SaaS\Http\Controllers\BillingController::class, 'plans']);
    Route::get('/payment/methods', [\Modules\Payments\Http\Controllers\FiatPaymentController::class, 'methods']);
    Route::get('/ad/spaces', [\Modules\AdMonetization\Http\Controllers\AdMonetizationController::class, 'availableSpaces']);
    Route::get('/revenue/streams', [\Modules\MultiRevenue\Http\Controllers\MultiRevenueController::class, 'revenueStreams']);

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'zero.trust', 'device.trusted'])->group(function () {
        
        // Auth
        Route::prefix('auth')->group(base_path('Modules/Auth/routes/api.php'));
        
        // Verification
        Route::prefix('verification')->group(base_path('Modules/Verification/routes/api.php'));
        
        // Security
        Route::prefix('security')->group(base_path('Modules/Security/routes/api.php'));
        
        // Image Generation
        Route::prefix('image')->group(base_path('Modules/ImageGeneration/routes/api.php'));
        
        // Video Generation
        Route::prefix('video')->group(base_path('Modules/VideoGeneration/routes/api.php'));
        
        // Text Generation
        Route::prefix('text')->group(base_path('Modules/TextGeneration/routes/api.php'));
        
        // Body Mapping
        Route::prefix('body')->group(base_path('Modules/BodyMapping/routes/api.php'));
        
        // SaaS Billing
        Route::prefix('billing')->group(base_path('Modules/SaaS/routes/api.php'));
        
        // Payments (Crypto + Fiat)
        Route::prefix('payments')->group(base_path('Modules/Payments/routes/api.php'));
        
        // Income Automation
        Route::prefix('income')->group(base_path('Modules/IncomeAutomation/routes/api.php'));
        
        // Ad Monetization
        Route::prefix('ads')->group(base_path('Modules/AdMonetization/routes/api.php'));
        
        // Multi-Revenue Streams
        Route::prefix('revenue')->group(base_path('Modules/MultiRevenue/routes/api.php'));
        
        // Analytics
        Route::prefix('analytics')->group(base_path('Modules/Analytics/routes/api.php'));
    });

    // Admin (requires admin role)
    Route::prefix('admin')->middleware(['auth:sanctum', 'zero.trust', 'role:admin'])->group(base_path('Modules/Admin/routes/api.php'));
});
