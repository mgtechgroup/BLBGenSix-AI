<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\IncomeStream;

class HomeController extends Controller
{
    public function landing()
    {
        return inertia('Landing');
    }

    public function dashboard()
    {
        $user = auth()->user();

        return inertia('Dashboard', [
            'user' => $user,
            'subscription' => $user->subscriptions()->latest()->first(),
            'recent_generations' => $user->generations()->latest()->take(10)->get(),
            'income_streams' => $user->incomeStreams()->get(),
            'total_revenue' => $user->incomeStreams()->sum('total_revenue'),
            'usage_today' => [
                'images' => $user->generations()->byType(Generation::TYPE_IMAGE)->whereDate('created_at', today())->count(),
                'videos' => $user->generations()->byType(Generation::TYPE_VIDEO)->whereDate('created_at', today())->count(),
                'text' => $user->generations()->byType(Generation::TYPE_TEXT)->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
