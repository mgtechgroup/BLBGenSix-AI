<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\SaaS\Services\BillingService;

class StripeWebhookController extends Controller
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        
        try {
            $this->billingService->handleWebhook($payload);
            return response()->json(['message' => 'Webhook handled']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
