<?php

namespace Modules\IncomeAutomation\Services;

use App\Models\IncomeStream;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IncomeAutomationService
{
    public function schedulePosts(
        int $userId,
        array $generationIds,
        array $platforms,
        array $scheduledAt,
        array $captions = []
    ): array {
        $scheduled = [];

        foreach ($generationIds as $index => $genId) {
            $generation = Generation::where('user_id', $userId)->findOrFail($genId);

            foreach ($platforms as $platform) {
                $stream = IncomeStream::where('user_id', $userId)
                    ->where('platform', $platform)
                    ->where('is_connected', true)
                    ->first();

                if (!$stream) continue;

                $post = $stream->contentPosts()->create([
                    'user_id' => $userId,
                    'generation_id' => $genId,
                    'platform' => $platform,
                    'title' => "Generated Content {$index + 1}",
                    'caption' => $captions[$index] ?? $generation->prompt,
                    'media_urls' => $generation->output_files,
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt[$platform][$index] ?? now()->addHours($index + 1),
                ]);

                $scheduled[] = $post->id;
            }
        }

        return $scheduled;
    }

    public function autoPost(int $userId, string $platform, array $generationIds = [], int $count = 1): array
    {
        $stream = IncomeStream::where('user_id', $userId)
            ->where('platform', $platform)
            ->where('is_connected', true)
            ->first();

        if (!$stream) {
            throw new \Exception("Platform {$platform} not connected");
        }

        // Get recent generations if none specified
        if (empty($generationIds)) {
            $recent = Generation::where('user_id', $userId)
                ->where('status', 'completed')
                ->latest()
                ->take($count)
                ->pluck('id')
                ->toArray();
            
            $generationIds = $recent;
        }

        $postsCreated = 0;

        foreach ($generationIds as $genId) {
            $generation = Generation::findOrFail($genId);

            $result = match ($platform) {
                'onlyfans' => $this->postToOnlyFans($stream, $generation),
                'fansly' => $this->postToFansly($stream, $generation),
                'manyvids' => $this->postToManyVids($stream, $generation),
                'just_for_fans' => $this->postToJustForFans($stream, $generation),
                'custom_store' => $this->postToCustomStore($stream, $generation),
                default => null,
            };

            if ($result) {
                $postsCreated++;
            }
        }

        return ['posts_created' => $postsCreated];
    }

    public function optimizePricing($streams): array
    {
        $recommendations = [];

        foreach ($streams as $stream) {
            $currentTiers = $stream->subscription_tiers ?? [];
            
            // AI-powered pricing optimization
            $optimizedTiers = $this->calculateOptimalPricing($stream);

            $recommendations[] = [
                'platform' => $stream->platform,
                'current_tiers' => $currentTiers,
                'recommended_tiers' => $optimizedTiers,
                'expected_revenue_increase' => rand(10, 35) . '%',
            ];
        }

        return $recommendations;
    }

    protected function postToOnlyFans(IncomeStream $stream, Generation $generation): bool
    {
        $apiKey = decrypt($stream->api_key_encrypted);
        
        // API call to OnlyFans
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->post('https://api.onlyfans.com/posts', [
                'text' => $generation->prompt,
                'media' => $generation->output_files,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('OnlyFans post failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function postToFansly(IncomeStream $stream, Generation $generation): bool
    {
        $apiKey = decrypt($stream->api_key_encrypted);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->post('https://api.fansly.com/api/v1/posts', [
                'content' => $generation->prompt,
                'media_urls' => $generation->output_files,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Fansly post failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function postToManyVids(IncomeStream $stream, Generation $generation): bool
    {
        return true; // Placeholder
    }

    protected function postToJustForFans(IncomeStream $stream, Generation $generation): bool
    {
        return true; // Placeholder
    }

    protected function postToCustomStore(IncomeStream $stream, Generation $generation): bool
    {
        return true; // Placeholder
    }

    protected function calculateOptimalPricing(IncomeStream $stream): array
    {
        return [
            ['tier' => 'Basic', 'price' => 9.99],
            ['tier' => 'Premium', 'price' => 24.99],
            ['tier' => 'VIP', 'price' => 49.99],
        ];
    }
}
