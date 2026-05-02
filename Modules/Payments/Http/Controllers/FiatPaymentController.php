<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FiatPaymentController extends Controller
{
    public function methods()
    {
        return response()->json([
            'available_methods' => [
                [
                    'id' => 'stripe',
                    'name' => 'Credit/Debit Card',
                    'provider' => 'Stripe',
                    'supported_cards' => ['Visa', 'Mastercard', 'Amex', 'Discover'],
                    'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                    'enabled' => true,
                ],
                [
                    'id' => 'paypal',
                    'name' => 'PayPal',
                    'provider' => 'PayPal',
                    'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
                    'enabled' => true,
                ],
                [
                    'id' => 'cashapp',
                    'name' => 'Cash App Pay',
                    'provider' => 'Block',
                    'currencies' => ['USD'],
                    'enabled' => true,
                ],
                [
                    'id' => 'apple_pay',
                    'name' => 'Apple Pay',
                    'provider' => 'Stripe',
                    'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                    'enabled' => true,
                ],
                [
                    'id' => 'google_pay',
                    'name' => 'Google Pay',
                    'provider' => 'Stripe',
                    'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                    'enabled' => true,
                ],
                [
                    'id' => 'sepa',
                    'name' => 'SEPA Direct Debit',
                    'provider' => 'Stripe',
                    'currencies' => ['EUR'],
                    'enabled' => true,
                ],
                [
                    'id' === 'ach',
                    'name' => 'ACH Bank Transfer',
                    'provider' => 'Stripe',
                    'currencies' => ['USD'],
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function payWithPayPal(Request $request)
    {
        return response()->json(['redirect_url' => 'https://paypal.com/checkout/...']);
    }

    public function payWithCashApp(Request $request)
    {
        return response()->json(['redirect_url' => 'https://cash.app/pay/...']);
    }
}
