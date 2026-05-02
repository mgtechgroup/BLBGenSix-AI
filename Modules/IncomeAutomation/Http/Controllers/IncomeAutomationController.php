<?php

namespace Modules\IncomeAutomation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\IncomeStream;
use App\Models\Generation;
use Illuminate\Http\Request;
use Modules\IncomeAutomation\Services\IncomeAutomationService;

class IncomeAutomationController extends Controller
{
    protected IncomeAutomationService $incomeService;

    public function __construct(IncomeAutomationService $incomeService)
    {
        $this->incomeService = $incomeService;
    }

    public function dashboard()
    {
        $user = auth()->user();
        
        $totalRevenue = $user->incomeStreams()->sum('total_revenue');
        $monthlyRevenue = $user->incomeStreams()->sum('monthly_revenue');
        $totalSubscribers = $user->incomeStreams()->sum('subscriber_count');
        $connectedPlatforms = $user->incomeStreams()->where('is_connected', true)->count();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'total_subscribers' => $totalSubscribers,
            'connected_platforms' => $connectedPlatforms,
            'active_streams' => $user->incomeStreams()->where('is_active', true)->count(),
        ]);
    }

    public function revenue()
    {
        $records = auth()->user()
            ->revenueRecords()
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->get()
            ->groupBy('platform');

        return response()->json([
            'total' => $records->flatten()->sum('amount'),
            'by_platform' => $records->map->sum('amount'),
            'by_day' => $records->flatten()->groupBy(fn($r) => $r->transaction_date->format('Y-m-d'))
                ->map->sum('amount'),
        ]);
    }

    public function connectPlatform(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:onlyfans,fansly,manyvids,just_for_fans,custom_store',
            'api_key' => 'required|string',
            'account_id' => 'nullable|string',
            'auto_post' => 'boolean|default:false',
            'posting_schedule' => 'nullable|array',
        ]);

        $stream = IncomeStream::create([
            'user_id' => auth()->id(),
            'platform' => $validated['platform'],
            'platform_account_id' => $validated['account_id'],
            'api_key_encrypted' => encrypt($validated['api_key']),
            'is_connected' => true,
            'is_active' => true,
            'auto_post_enabled' => $validated['auto_post'],
            'posting_schedule' => $validated['posting_schedule'],
        ]);

        return response()->json([
            'success' => true,
            'stream_id' => $stream->id,
            'platform' => $stream->platform,
        ]);
    }

    public function disconnectPlatform(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|string',
        ]);

        IncomeStream::where('user_id', auth()->id())
            ->where('platform', $validated['platform'])
            ->update(['is_connected' => false, 'is_active' => false]);

        return response()->json(['success' => true]);
    }

    public function platforms()
    {
        return response()->json([
            'connected' => auth()->user()->incomeStreams()->get(),
            'available' => [
                'onlyfans' => ['name' => 'OnlyFans', 'supported' => true],
                'fansly' => ['name' => 'Fansly', 'supported' => true],
                'manyvids' => ['name' => 'ManyVids', 'supported' => true],
                'just_for_fans' => ['name' => 'JustForFans', 'supported' => true],
                'custom_store' => ['name' => 'Custom Store', 'supported' => true],
            ]
        ]);
    }

    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'generation_ids' => 'required|array',
            'platforms' => 'required|array',
            'scheduled_at' => 'required|array',
            'captions' => 'nullable|array',
        ]);

        $scheduled = $this->incomeService->schedulePosts(
            auth()->id(),
            $validated['generation_ids'],
            $validated['platforms'],
            $validated['scheduled_at'],
            $validated['captions'] ?? []
        );

        return response()->json([
            'success' => true,
            'scheduled_count' => count($scheduled),
        ]);
    }

    public function getSchedule()
    {
        $posts = auth()->user()
            ->contentPosts()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['scheduled_posts' => $posts]);
    }

    public function autoPost(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|string',
            'generation_ids' => 'nullable|array',
            'count' => 'integer|min:1|max:10|default:1',
        ]);

        $result = $this->incomeService->autoPost(
            auth()->id(),
            $validated['platform'],
            $validated['generation_ids'] ?? [],
            $validated['count']
        );

        return response()->json([
            'success' => true,
            'posts_created' => $result['posts_created'],
        ]);
    }

    public function toggleAutoPost(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|string',
        ]);

        $stream = IncomeStream::where('user_id', auth()->id())
            ->where('platform', $validated['platform'])
            ->firstOrFail();

        $stream->update(['auto_post_enabled' => !$stream->auto_post_enabled]);

        return response()->json([
            'success' => true,
            'auto_post_enabled' => $stream->auto_post_enabled,
        ]);
    }

    public function analytics()
    {
        return response()->json([
            'revenue_trend' => [],
            'top_performing_content' => [],
            'platform_breakdown' => [],
            'subscriber_growth' => [],
        ]);
    }

    public function payouts()
    {
        return response()->json([
            'pending' => 0,
            'processed' => [],
            'total_lifetime' => 0,
        ]);
    }

    public function optimizePricing()
    {
        $user = auth()->user();
        $streams = $user->incomeStreams()->where('is_connected', true)->get();

        $optimized = $this->incomeService->optimizePricing($streams);

        return response()->json([
            'success' => true,
            'recommendations' => $optimized,
        ]);
    }

    public function streams()
    {
        return response()->json([
            'streams' => auth()->user()->incomeStreams()->get(),
        ]);
    }

    public function createStream(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'platform' => 'required|string',
            'tier_prices' => 'required|array',
        ]);

        $stream = IncomeStream::create([
            'user_id' => auth()->id(),
            'platform' => $validated['platform'],
            'subscription_tiers' => $validated['tier_prices'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'stream_id' => $stream->id,
        ]);
    }

    public function deleteStream($id)
    {
        IncomeStream::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
