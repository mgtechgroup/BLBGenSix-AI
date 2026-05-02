<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $limits = $this->getUserLimits($user);
        $today = now()->startOfDay();

        $todayGenerations = $user->generations()
            ->whereDate('created_at', $today)
            ->count();

        return match ($request->segment(3)) {
            'image' => $this->checkLimit($todayGenerations, $limits['images_per_day'], 'images'),
            'video' => $this->checkLimit($todayGenerations, $limits['videos_per_day'], 'videos'),
            'text' => $this->checkLimit($todayGenerations, $limits['text_tokens_per_day'], 'text tokens'),
            'body' => $this->checkLimit($todayGenerations, $limits['body_models_per_day'], 'body models'),
            default => $next($request),
        };
    }

    private function getUserLimits($user): array
    {
        if ($user->isActiveSubscriber()) {
            return $user->subscriptions()->latest()->first()?->getUsageLimits() ?? [
                'images_per_day' => 50,
                'videos_per_day' => 5,
                'text_tokens_per_day' => 50000,
                'body_models_per_day' => 3,
            ];
        }

        return [
            'images_per_day' => 0,
            'videos_per_day' => 0,
            'text_tokens_per_day' => 0,
            'body_models_per_day' => 0,
        ];
    }

    private function checkLimit($current, $limit, $type)
    {
        if ($limit === -1) {
            return true; // Unlimited
        }

        if ($current >= $limit) {
            return response()->json([
                'error' => 'Daily limit exceeded',
                'type' => $type,
                'current' => $current,
                'limit' => $limit,
                'upgrade_url' => route('billing.plans')
            ], 429);
        }

        return true;
    }
}
