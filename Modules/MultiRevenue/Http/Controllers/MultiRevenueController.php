<?php

namespace Modules\MultiRevenue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MultiRevenueController extends Controller
{
    public function revenueStreams()
    {
        return response()->json([
            'streams' => [
                [
                    'id' => 'subscriptions',
                    'name' => 'Subscriptions',
                    'description' => 'Monthly recurring revenue from platform subscriptions',
                    'tiers' => [
                        ['name' => 'Starter', 'price' => 29.99],
                        ['name' => 'Pro', 'price' => 99.99],
                        ['name' => 'Enterprise', 'price' => 299.99],
                    ],
                ],
                [
                    'id' => 'generation_credits',
                    'name' => 'Generation Credits',
                    'description' => 'Pay-per-use credits for AI generation',
                    'pricing' => [
                        ['amount' => 100, 'price' => 9.99],
                        ['amount' => 500, 'price' => 39.99],
                        ['amount' => 2000, 'price' => 129.99],
                    ],
                ],
                [
                    'id' => 'tips',
                    'name' => 'Tips & Donations',
                    'description' => 'Receive tips from fans in crypto or fiat',
                    'min_amount' => 1.00,
                    'supported_currencies' => ['USD', 'ETH', 'BTC', 'SOL', 'MATIC'],
                ],
                [
                    'id' => 'ppv',
                    'name' => 'Pay-Per-View Content',
                    'description' => 'Sell individual pieces of generated content',
                    'pricing_range' => '$4.99 - $99.99',
                ],
                [
                    'id' => 'bundles',
                    'name' => 'Content Bundles',
                    'description' => 'Package multiple generations at a discount',
                    'pricing_range' => '$19.99 - $499.99',
                ],
                [
                    'id' => 'affiliate',
                    'name' => 'Affiliate Program',
                    'description' => 'Earn 20% recurring commission on referrals',
                    'commission_rate' => 0.20,
                    'cookie_days' => 90,
                ],
                [
                    'id' => 'ad_space',
                    'name' => 'Ad Space Sales',
                    'description' => 'Monetize your audience with ad placements',
                    'cpm_range' => '$8 - $40',
                ],
                [
                    'id' => 'api_access',
                    'name' => 'API Access',
                    'description' => 'Sell API access to your generated content',
                    'pricing' => '$49.99/month per endpoint',
                ],
                [
                    'id' => 'custom_commissions',
                    'name' => 'Custom Commissions',
                    'description' => 'Accept custom generation requests at premium pricing',
                    'pricing' => 'Starting at $49.99',
                ],
                [
                    'id' => 'nft_sales',
                    'name' => 'NFT Sales',
                    'description' => 'Mint and sell generated content as NFTs',
                    'supported_chains' => ['ethereum', 'polygon', 'solana'],
                ],
            ],
        ]);
    }

    public function sendTip(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|uuid',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|in:USD,ETH,BTC,SOL,MATIC',
            'payment_method' => 'required|string|in:stripe,paypal,cashapp,crypto',
            'message' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tip sent',
        ]);
    }

    public function createPPVContent(Request $request)
    {
        $validated = $request->validate([
            'generation_id' => 'required|uuid',
            'title' => 'required|string|max:200',
            'price' => 'required|numeric|min:0.99',
            'currency' => 'string|default:USD',
            'crypto_price' => 'nullable|numeric',
            'crypto_token' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'ppv_id' => uniqid('ppv_'),
            'price' => $validated['price'],
        ]);
    }

    public function createBundle(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:2000',
            'generation_ids' => 'required|array|min:2|max:50',
            'price' => 'required|numeric|min:4.99',
            'discount_percent' => 'integer|min:10|max:80',
        ]);

        return response()->json([
            'success' => true,
            'bundle_id' => uniqid('bundle_'),
        ]);
    }

    public function affiliateLink()
    {
        $user = auth()->user();
        $code = $user->affiliate_code ?? $this->generateAffiliateCode($user);

        return response()->json([
            'affiliate_code' => $code,
            'referral_link' => "https://blbgensixai.club/register?ref={$code}",
            'commission_rate' => '20%',
            'total_earnings' => $user->affiliate_earnings ?? 0,
            'total_referrals' => $user->referral_count ?? 0,
        ]);
    }

    public function createNFT(Request $request)
    {
        $validated = $request->validate([
            'generation_id' => 'required|uuid',
            'chain' => 'required|string|in:ethereum,polygon,solana',
            'price' => 'required|numeric|min:0.001',
            'currency' => 'string|default:ETH',
        ]);

        return response()->json([
            'success' => true,
            'nft_id' => uniqid('nft_'),
            'chain' => $validated['chain'],
            'status' => 'minting',
        ]);
    }

    protected function generateAffiliateCode($user): string
    {
        $code = strtoupper(substr($user->username, 0, 4)) . '-' . substr(md5($user->id), 0, 6);
        $user->update(['affiliate_code' => $code]);
        return $code;
    }
}
