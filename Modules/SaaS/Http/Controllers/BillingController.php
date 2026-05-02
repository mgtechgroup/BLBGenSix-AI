<?php

namespace Modules\SaaS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Modules\SaaS\Services\BillingService;

class BillingController extends Controller
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function plans()
    {
        return response()->json([
            'plans' => [
                [
                    'id' => 'starter',
                    'name' => 'Starter',
                    'price' => 29.99,
                    'billing_period' => 'monthly',
                    'features' => [
                        '50 images/day',
                        '5 videos/day',
                        '50K tokens/day',
                        '3 body models/day',
                        '1024x1024 max resolution',
                        '30s max video',
                        'Standard queue',
                        '1 income stream',
                    ],
                    'limits' => [
                        'images_per_day' => 50,
                        'videos_per_day' => 5,
                        'text_tokens_per_day' => 50000,
                        'body_models_per_day' => 3,
                    ],
                ],
                [
                    'id' => 'pro',
                    'name' => 'Professional',
                    'price' => 99.99,
                    'billing_period' => 'monthly',
                    'popular' => true,
                    'features' => [
                        '500 images/day',
                        '50 videos/day',
                        '500K tokens/day',
                        '20 body models/day',
                        '2048x2048 max resolution',
                        '120s max video',
                        'Priority queue',
                        '5 income streams',
                        'Analytics dashboard',
                        'API access',
                    ],
                    'limits' => [
                        'images_per_day' => 500,
                        'videos_per_day' => 50,
                        'text_tokens_per_day' => 500000,
                        'body_models_per_day' => 20,
                    ],
                ],
                [
                    'id' => 'enterprise',
                    'name' => 'Enterprise',
                    'price' => 299.99,
                    'billing_period' => 'monthly',
                    'features' => [
                        'Unlimited everything',
                        '4K resolution',
                        '300s max video',
                        'Priority queue',
                        'Unlimited income streams',
                        'Advanced analytics',
                        'Full API access',
                        'Custom models',
                        'Dedicated support',
                        'White-label option',
                    ],
                    'limits' => [
                        'images_per_day' => -1,
                        'videos_per_day' => -1,
                        'text_tokens_per_day' => -1,
                        'body_models_per_day' => -1,
                    ],
                ],
            ],
            'trial' => [
                'days' => 7,
                'includes' => 'Starter plan features',
            ],
        ]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:starter,pro,enterprise',
            'payment_method' => 'required|string',
            'billing_period' => 'string|in:monthly,yearly|default:monthly',
        ]);

        $user = auth()->user();

        $session = $this->billingService->createCheckoutSession(
            $user,
            $validated['plan'],
            $validated['payment_method'],
            $validated['billing_period']
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'checkout_url' => $session->url,
        ]);
    }

    public function cancel()
    {
        $user = auth()->user();
        $subscription = $user->subscriptions()->latest()->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription'], 404);
        }

        $this->billingService->cancelSubscription($subscription);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled. Access until ' . $subscription->current_period_end,
        ]);
    }

    public function resume()
    {
        $user = auth()->user();
        $subscription = $user->subscriptions()->where('status', 'cancelled')->latest()->first();

        if (!$subscription) {
            return response()->json(['error' => 'No cancelled subscription found'], 404);
        }

        $this->billingService->resumeSubscription($subscription);

        return response()->json(['success' => true, 'message' => 'Subscription resumed']);
    }

    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'new_plan' => 'required|string|in:pro,enterprise',
        ]);

        $user = auth()->user();
        $current = $user->subscriptions()->latest()->first();

        $this->billingService->upgradeSubscription($current, $validated['new_plan']);

        return response()->json(['success' => true, 'message' => 'Upgraded to ' . $validated['new_plan']]);
    }

    public function downgrade(Request $request)
    {
        $validated = $request->validate([
            'new_plan' => 'required|string|in:starter,pro',
        ]);

        $user = auth()->user();
        $current = $user->subscriptions()->latest()->first();

        $this->billingService->downgradeSubscription($current, $validated['new_plan']);

        return response()->json(['success' => true, 'message' => 'Downgrade scheduled']);
    }

    public function invoices()
    {
        $user = auth()->user();
        $customer = $user->subscriptions()->latest()->first()?->stripe_customer_id;

        if (!$customer) {
            return response()->json(['invoices' => []]);
        }

        $invoices = \Stripe\Invoice::all(['customer' => $customer, 'limit' => 50]);

        return response()->json(['invoices' => $invoices->data]);
    }

    public function downloadInvoice($id)
    {
        $invoice = \Stripe\Invoice::retrieve($id);
        
        return response()->json([
            'pdf_url' => $invoice->invoice_pdf,
            'hosted_url' => $invoice->hosted_invoice_url,
        ]);
    }

    public function usage()
    {
        $user = auth()->user();
        $limits = $user->subscriptions()->latest()->first()?->getUsageLimits() ?? [];
        $today = now()->startOfDay();

        $usage = [
            'images' => $user->generations()->byType('image')->whereDate('created_at', $today)->count(),
            'videos' => $user->generations()->byType('video')->whereDate('created_at', $today)->count(),
            'text' => $user->generations()->byType('text')->whereDate('created_at', $today)->count(),
            'body' => $user->generations()->byType('body')->whereDate('created_at', $today)->count(),
        ];

        return response()->json([
            'usage' => $usage,
            'limits' => $limits,
            'remaining' => [
                'images' => $limits['images_per_day'] === -1 ? -1 : max(0, ($limits['images_per_day'] ?? 0) - $usage['images']),
                'videos' => $limits['videos_per_day'] === -1 ? -1 : max(0, ($limits['videos_per_day'] ?? 0) - $usage['videos']),
            ],
        ]);
    }

    public function limits()
    {
        $user = auth()->user();
        $limits = $user->subscriptions()->latest()->first()?->getUsageLimits() ?? [];

        return response()->json(['limits' => $limits]);
    }

    public function updatePaymentMethod(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
        ]);

        $user = auth()->user();
        $customer = $user->subscriptions()->latest()->first()?->stripe_customer_id;

        if ($customer) {
            \Stripe\Customer::update($customer, [
                'invoice_settings' => [
                    'default_payment_method' => $validated['payment_method'],
                ],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function receipts()
    {
        return response()->json(['receipts' => []]);
    }
}
