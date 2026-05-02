<?php

namespace Modules\AdMonetization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdSpace;
use App\Models\AdCampaign;
use Illuminate\Http\Request;

class AdMonetizationController extends Controller
{
    public function availableSpaces()
    {
        return response()->json([
            'spaces' => [
                ['id' => 'header_banner', 'name' => 'Header Banner', 'size' => '728x90', 'location' => 'top', 'cpm_base' => 15.00, 'adult_safe' => true],
                ['id' => 'sidebar_rectangle', 'name' => 'Sidebar Rectangle', 'size' => '300x250', 'location' => 'sidebar', 'cpm_base' => 12.00, 'adult_safe' => true],
                ['id' => 'content_native', 'name' => 'Native Content Ad', 'size' => 'fluid', 'location' => 'inline', 'cpm_base' => 18.00, 'adult_safe' => true],
                ['id' => 'interstitial', 'name' => 'Interstitial Full-Screen', 'size' => 'full', 'location' => 'between_pages', 'cpm_base' => 25.00, 'adult_safe' => true],
                ['id' => 'video_pre_roll', 'name' => 'Video Pre-Roll', 'size' => '1920x1080', 'location' => 'before_video', 'cpm_base' => 35.00, 'adult_safe' => true],
                ['id' => 'video_mid_roll', 'name' => 'Video Mid-Roll', 'size' => '1920x1080', 'location' => 'during_video', 'cpm_base' => 30.00, 'adult_safe' => true],
                ['id' => 'video_post_roll', 'name' => 'Video Post-Roll', 'size' => '1920x1080', 'location' => 'after_video', 'cpm_base' => 20.00, 'adult_safe' => true],
                ['id' => 'popup_overlay', 'name' => 'Popup Overlay', 'size' => 'responsive', 'location' => 'overlay', 'cpm_base' => 22.00, 'adult_safe' => true],
                ['id' => 'footer_sticky', 'name' => 'Footer Sticky Bar', 'size' => '320x50', 'location' => 'bottom', 'cpm_base' => 8.00, 'adult_safe' => true],
                ['id' => 'notification_push', 'name' => 'Push Notification Ad', 'size' => 'notification', 'location' => 'push', 'cpm_base' => 40.00, 'adult_safe' => true],
            ],
            'adult_content_policy' => 'All ad content must comply with 18+ content guidelines. No illegal content.',
        ]);
    }

    public function bookSpace(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|string',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'budget_total' => 'required|numeric|min:100',
            'creative_url' => 'required|string',
            'target_audience' => 'nullable|array',
            'payment_method' => 'required|string|in:stripe,paypal,cashapp,crypto_ethereum,crypto_bitcoin,crypto_polygon,crypto_solana',
        ]);

        $campaign = AdCampaign::create([
            'user_id' => auth()->id(),
            'space_id' => $validated['space_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'budget_total' => $validated['budget_total'],
            'budget_remaining' => $validated['budget_total'],
            'creative_url' => $validated['creative_url'],
            'target_audience' => $validated['target_audience'],
            'payment_method' => $validated['payment_method'],
            'status' => 'pending_approval',
        ]);

        return response()->json([
            'success' => true,
            'campaign_id' => $campaign->id,
            'message' => 'Campaign submitted for approval. Adult content review required.',
        ], 201);
    }

    public function myCampaigns()
    {
        $campaigns = AdCampaign::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return response()->json($campaigns);
    }

    public function revenue()
    {
        $user = auth()->user();
        
        return response()->json([
            'total_ad_revenue' => $user->adRevenueRecords()->sum('amount'),
            'this_month' => $user->adRevenueRecords()
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            'by_space' => $user->adRevenueRecords()
                ->selectRaw('space_id, SUM(amount) as total')
                ->groupBy('space_id')
                ->get(),
            'impressions_total' => AdSpace::where('user_id', auth()->id())->sum('impressions'),
            'click_through_rate' => AdSpace::where('user_id', auth()->id())->avg('ctr'),
        ]);
    }

    public function reportImpression(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|string',
            'campaign_id' => 'nullable|uuid',
            'view_time_ms' => 'integer|min:0',
        ]);

        AdSpace::where('space_id', $validated['space_id'])
            ->increment('impressions');

        return response()->json(['recorded' => true]);
    }

    public function reportClick(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|string',
            'campaign_id' => 'nullable|uuid',
        ]);

        AdSpace::where('space_id', $validated['space_id'])
            ->increment('clicks');

        return response()->json(['recorded' => true]);
    }
}
