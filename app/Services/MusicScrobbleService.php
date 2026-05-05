<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MusicScrobbleService
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected int $cacheTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.multi_scrobbler.base_url', env('MULTI_SCROBBLER_URL', 'http://multi-scrobbler:9078')), '/');
        $this->apiKey = config('services.multi_scrobbler.api_key', env('MULTI_SCROBBLER_API_KEY'));
        $this->cacheTtl = config('services.multi_scrobbler.cache_ttl', 300);
    }

    public function getStatus(): array
    {
        $cacheKey = 'music_scrobbler:status';

        return Cache::remember($cacheKey, $this->cacheTtl, function (): array {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/api/health");

                if ($response->failed()) {
                    Log::warning('Multi-scrobbler health check failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [
                        'status' => 'error',
                        'message' => 'Service unreachable',
                        'checked_at' => now()->toIso8601String(),
                    ];
                }

                $data = $response->json();

                return [
                    'status' => $data['status'] ?? 'unknown',
                    'version' => $data['version'] ?? null,
                    'uptime' => $data['uptime'] ?? null,
                    'active_sources' => $data['active_clients'] ?? 0,
                    'active_targets' => $data['active_targets'] ?? 0,
                    'checked_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error('Multi-scrobbler connection error', [
                    'error' => $e->getMessage(),
                    'url' => $this->baseUrl,
                ]);

                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'checked_at' => now()->toIso8601String(),
                ];
            }
        });
    }

    public function getRecentPlays(int $limit = 20, ?string $source = null): array
    {
        $cacheKey = "music_scrobbler:recent:{$source}:{$limit}";

        return Cache::remember($cacheKey, 60, function () use ($limit, $source): array {
            try {
                $params = ['limit' => $limit];

                if ($source) {
                    $params['source'] = $source;
                }

                $response = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/api/plays/recent", $params);

                if ($response->failed()) {
                    Log::warning('Failed to fetch recent plays', [
                        'status' => $response->status(),
                        'source' => $source,
                    ]);

                    return [];
                }

                $data = $response->json()['plays'] ?? [];

                return array_map(fn(array $play) => $this->mapPlayObject($play), $data);
            } catch (\Exception $e) {
                Log::error('Error fetching recent plays', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    public function getScrobbleHistory(
        string $userId,
        ?string $source = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $cacheKey = "music_scrobbler:history:{$userId}:{$source}:{$startDate}:{$endDate}:{$limit}:{$offset}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use (
            $userId,
            $source,
            $startDate,
            $endDate,
            $limit,
            $offset
        ): array {
            try {
                $params = [
                    'limit' => $limit,
                    'offset' => $offset,
                ];

                if ($source) {
                    $params['source'] = $source;
                }

                if ($startDate) {
                    $params['start_date'] = $startDate;
                }

                if ($endDate) {
                    $params['end_date'] = $endDate;
                }

                $response = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/api/plays/history", $params);

                if ($response->failed()) {
                    Log::warning('Failed to fetch scrobble history', [
                        'status' => $response->status(),
                        'user_id' => $userId,
                    ]);

                    return ['plays' => [], 'total' => 0, 'has_more' => false];
                }

                $data = $response->json();
                $plays = $data['plays'] ?? [];

                return [
                    'plays' => array_map(fn(array $play) => $this->mapPlayObject($play), $plays),
                    'total' => $data['total'] ?? count($plays),
                    'has_more' => $data['has_more'] ?? false,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
            } catch (\Exception $e) {
                Log::error('Error fetching scrobble history', ['error' => $e->getMessage()]);
                return ['plays' => [], 'total' => 0, 'has_more' => false];
            }
        });
    }

    public function getSourceHealth(): array
    {
        $cacheKey = 'music_scrobbler:sources_health';

        return Cache::remember($cacheKey, 120, function (): array {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/api/sources");

                if ($response->failed()) {
                    Log::warning('Failed to fetch source health', [
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $sources = $response->json()['sources'] ?? [];

                return array_map(function (array $source): array {
                    return [
                        'id' => $source['id'] ?? null,
                        'name' => $source['name'] ?? 'Unknown',
                        'type' => $source['type'] ?? 'unknown',
                        'status' => $source['status'] ?? 'disconnected',
                        'connected' => $source['connected'] ?? false,
                        'last_scrobble' => $source['last_scrobble'] ?? null,
                        'scrobble_count' => $source['scrobble_count'] ?? 0,
                        'error' => $source['error'] ?? null,
                        'auth_valid' => $source['auth_valid'] ?? false,
                        'updated_at' => $source['updated_at'] ?? null,
                    ];
                }, $sources);
            } catch (\Exception $e) {
                Log::error('Error fetching source health', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    public function invalidateCache(): void
    {
        Cache::forget('music_scrobbler:status');
        Cache::forget('music_scrobbler:sources_health');

        $tags = Cache::tags(['music_scrobbler']);

        if (method_exists($tags, 'flush')) {
            Cache::flush();
        }
    }

    protected function mapPlayObject(array $play): array
    {
        $track = $play['track'] ?? [];
        $artist = $play['artist'] ?? [];
        $album = $play['album'] ?? [];

        return [
            'id' => $play['id'] ?? null,
            'track_name' => $track['name'] ?? $play['track_name'] ?? 'Unknown Track',
            'artist_name' => $artist['name'] ?? $play['artist_name'] ?? 'Unknown Artist',
            'artist_mbid' => $artist['mbid'] ?? null,
            'album_name' => $album['name'] ?? $play['album_name'] ?? null,
            'album_mbid' => $album['mbid'] ?? null,
            'track_mbid' => $track['mbid'] ?? null,
            'played_at' => $play['played_at'] ?? $play['timestamp'] ?? null,
            'duration' => $play['duration'] ?? $track['duration'] ?? null,
            'source' => $play['source'] ?? 'unknown',
            'source_type' => $play['source_type'] ?? null,
            'play_type' => $play['play_type'] ?? 'scrobble',
            'is_loved' => $play['loved'] ?? false,
        ];
    }
}
