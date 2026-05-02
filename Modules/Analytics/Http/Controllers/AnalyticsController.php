<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\IncomeStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview()
    {
        $user = auth()->user();
        $thirtyDays = now()->subDays(30);

        return response()->json([
            'total_generations' => $user->generations()->count(),
            'generations_this_month' => $user->generations()->where('created_at', '>=', $thirtyDays)->count(),
            'total_revenue' => $user->incomeStreams()->sum('total_revenue'),
            'revenue_this_month' => $user->incomeStreams()->sum('monthly_revenue'),
            'total_subscribers' => $user->incomeStreams()->sum('subscriber_count'),
            'connected_platforms' => $user->incomeStreams()->where('is_connected', true)->count(),
        ]);
    }

    public function generations()
    {
        $user = auth()->user();

        return response()->json([
            'by_type' => $user->generations()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get(),
            'by_status' => $user->generations()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'avg_processing_time' => $user->generations()
                ->whereNotNull('processing_time')
                ->avg('processing_time'),
            'daily_trend' => $user->generations()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }

    public function revenue()
    {
        return response()->json([
            'total' => auth()->user()->incomeStreams()->sum('total_revenue'),
            'monthly' => auth()->user()->incomeStreams()->sum('monthly_revenue'),
            'by_platform' => auth()->user()
                ->incomeStreams()
                ->selectRaw('platform, SUM(total_revenue) as total')
                ->groupBy('platform')
                ->get(),
        ]);
    }

    public function engagement() { return response()->json(['data' => []]); }
    public function platformPerformance() { return response()->json(['data' => []]); }
    public function contentPerformance() { return response()->json(['data' => []]); }
    public function audience() { return response()->json(['data' => []]); }
    public function trends() { return response()->json(['data' => []]); }
    public function export() { return response()->json(['message' => 'Export generated']); }
    public function realtime() { return response()->json(['data' => []]); }
}
