<?php

namespace App\Jobs;

use App\Models\ListeningStat;
use App\Models\MusicSource;
use App\Models\MusicTasteProfile;
use App\Models\MusicWebhookEvent;
use App\Models\MusicAchievement;
use App\Services\MusicAnalyticsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScrobbleEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxAttempts = 5;
    public int $backoff = 10;
    public int $timeout = 120;
    public bool $retryUntil = 300;

    public function __construct(
        protected int $webhookEventId,
        protected ?int $userId,
        protected array $scrobbleData,
        protected Carbon $playedAt,
        protected string $trackId,
        protected string $eventType = 'scrobble.new'
    ) {
    }

    public function handle(MusicAnalyticsService $analytics): void
    {
        $webhookEvent = MusicWebhookEvent::find($this->webhookEventId);
        if (!$webhookEvent) {
            Log::warning('ProcessScrobbleEvent: webhook event not found', ['id' => $this->webhookEventId]);
            return;
        }

        if ($this->eventType === 'scrobble.new' && $this->isDuplicate()) {
            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => 'Duplicate scrobble skipped',
            ]);
            Log::info('ProcessScrobbleEvent: duplicate scrobble skipped', [
                'track_id' => $this->trackId,
                'user_id' => $this->userId,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($webhookEvent) {
                $listeningStat = $this->createOrUpdateListeningStat();

                if ($listeningStat) {
                    $this->updateMusicTasteProfile($listeningStat);
                    $this->checkAchievements($listeningStat);
                }

                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            });

            Log::info('ProcessScrobbleEvent: scrobble processed successfully', [
                'user_id' => $this->userId,
                'track' => data_get($this->scrobbleData, 'track_name') ?? data_get($this->scrobbleData, 'name'),
                'artist' => data_get($this->scrobbleData, 'artist_name') ?? data_get($this->scrobbleData, 'artist'),
            ]);
        } catch (\Exception $e) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('ProcessScrobbleEvent: failed to process scrobble', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function isDuplicate(): bool
    {
        return DB::table('listening_stats')->where(function ($query) {
            $query->where('user_id', $this->userId)
                  ->where('played_at', $this->playedAt);

            if (!empty($this->scrobbleData['track_mbid'])) {
                $query->orWhere(function ($q) {
                    $q->where('user_id', $this->userId)
                      ->where('track_mbid', $this->scrobbleData['track_mbid'])
                      ->where('played_at', '>=', $this->playedAt->copy()->subMinutes(5))
                      ->where('played_at', '<=', $this->playedAt->copy()->addMinutes(5));
                });
            }
        })->exists();
    }

    protected function createOrUpdateListeningStat(): ?ListeningStat
    {
        $trackName = data_get($this->scrobbleData, 'track_name') ?? data_get($this->scrobbleData, 'name') ?? 'Unknown';
        $artistName = data_get($this->scrobbleData, 'artist_name') ?? data_get($this->scrobbleData, 'artist') ?? 'Unknown';
        $albumName = data_get($this->scrobbleData, 'album_name') ?? data_get($this->scrobbleData, 'album') ?? null;
        $sourceType = data_get($this->scrobbleData, 'source') ?? data_get($this->scrobbleData, 'source_type');
        $durationMs = data_get($this->scrobbleData, 'duration_ms') ?? data_get($this->scrobbleData, 'duration') ?? null;
        $isLoved = data_get($this->scrobbleData, 'loved') ?? data_get($this->scrobbleData, 'is_loved') ?? false;
        $trackMbid = data_get($this->scrobbleData, 'track_mbid') ?? data_get($this->scrobbleData, 'mbid') ?? null;
        $artistMbid = data_get($this->scrobbleData, 'artist_mbid') ?? null;
        $albumMbid = data_get($this->scrobbleData, 'album_mbid') ?? null;
        $genreTags = data_get($this->scrobbleData, 'genre_tags') ?? data_get($this->scrobbleData, 'tags') ?? [];
        $trackFeatures = data_get($this->scrobbleData, 'features') ?? data_get($this->scrobbleData, 'track_features') ?? [];
        $metadata = data_get($this->scrobbleData, 'metadata') ?? [];

        if (is_string($genreTags)) {
            $genreTags = json_decode($genreTags, true) ?? [$genreTags];
        }

        $musicSource = null;
        if ($sourceType) {
            $musicSource = MusicSource::where('user_id', $this->userId)
                ->where('source_type', $sourceType)
                ->first();
        }

        $listeningStat = ListeningStat::create([
            'user_id' => $this->userId,
            'music_source_id' => $musicSource?->id,
            'track_name' => $trackName,
            'artist_name' => $artistName,
            'album_name' => $albumName,
            'track_mbid' => $trackMbid,
            'artist_mbid' => $artistMbid,
            'album_mbid' => $albumMbid,
            'source_type' => $sourceType,
            'duration_ms' => $durationMs ? (int) $durationMs : null,
            'played_at' => $this->playedAt,
            'is_loved' => (bool) $isLoved,
            'track_features' => !empty($trackFeatures) ? json_encode($trackFeatures) : null,
            'genre_tags' => !empty($genreTags) ? json_encode(array_map('strval', $genreTags)) : null,
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
        ]);

        if ($musicSource) {
            $musicSource->update([
                'last_scrobble_at' => $this->playedAt,
            ]);
        }

        return $listeningStat;
    }

    protected function updateMusicTasteProfile(ListeningStat $stat): void
    {
        $profile = MusicTasteProfile::firstOrCreate(
            ['user_id' => $this->userId],
            [
                'top_genres' => json_encode([]),
                'top_artists' => json_encode([]),
                'top_tracks' => json_encode([]),
                'mood_distribution' => json_encode([]),
                'listening_patterns' => json_encode([]),
            ]
        );

        $totalPlays = ($profile->total_plays ?? 0) + 1;
        $durationMinutes = ($stat->duration_ms ?? 0) / 60000;
        $totalHours = round(($profile->total_hours ?? 0) + ($durationMinutes / 60), 2);

        $streak = $this->calculateListeningStreak($profile);

        $profileUpdate = [
            'total_plays' => $totalPlays,
            'total_hours' => $totalHours,
            'listening_streak' => $streak,
            'last_listened_at' => $stat->played_at,
            'profile_updated_at' => now(),
        ];

        if ($totalPlays % 10 === 0 || $profile->top_genres === null || $profile->top_genres === '[]') {
            $genreDistribution = $this->computeGenreDistribution();
            $topArtists = $this->computeTopArtists();
            $topTracks = $this->computeTopTracks();

            $profileUpdate['top_genres'] = json_encode($genreDistribution);
            $profileUpdate['top_artists'] = json_encode($topArtists);
            $profileUpdate['top_tracks'] = json_encode($topTracks);
        }

        if ($stat->track_features) {
            $features = is_string($stat->track_features) ? json_decode($stat->track_features, true) : $stat->track_features;
            if (!empty($features['energy'])) {
                $currentAvg = $profile->avg_energy ?? 0;
                $profileUpdate['avg_energy'] = round(($currentAvg * ($totalPlays - 1) + $features['energy']) / $totalPlays, 4);
            }
            if (!empty($features['valence'])) {
                $currentAvg = $profile->avg_valence ?? 0;
                $profileUpdate['avg_valence'] = round(($currentAvg * ($totalPlays - 1) + $features['valence']) / $totalPlays, 4);
            }
            if (!empty($features['danceability'])) {
                $currentAvg = $profile->avg_danceability ?? 0;
                $profileUpdate['avg_danceability'] = round(($currentAvg * ($totalPlays - 1) + $features['danceability']) / $totalPlays, 4);
            }
            if (!empty($features['acousticness'])) {
                $currentAvg = $profile->avg_acousticness ?? 0;
                $profileUpdate['avg_acousticness'] = round(($currentAvg * ($totalPlays - 1) + $features['acousticness']) / $totalPlays, 4);
            }
        }

        $profile->update($profileUpdate);
    }

    protected function calculateListeningStreak(MusicTasteProfile $profile): int
    {
        $lastListened = $profile->last_listened_at ? \Carbon\Carbon::parse($profile->last_listened_at) : null;

        if (!$lastListened) {
            return 1;
        }

        $today = now()->startOfDay();
        $lastDate = $lastListened->startOfDay();
        $diffDays = $today->diffInDays($lastDate);

        if ($diffDays > 1) {
            return 1;
        }

        return ($profile->listening_streak ?? 0) + 1;
    }

    protected function computeGenreDistribution(): array
    {
        $genres = DB::table('listening_stats')
            ->where('user_id', $this->userId)
            ->whereNotNull('genre_tags')
            ->orderBy('played_at', 'desc')
            ->limit(1000)
            ->pluck('genre_tags');

        $genreCounts = [];

        foreach ($genres as $genreJson) {
            $tags = is_string($genreJson) ? json_decode($genreJson, true) : [];
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $genre = strtolower(trim((string) $tag));
                    if (!empty($genre)) {
                        $genreCounts[$genre] = ($genreCounts[$genre] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($genreCounts);

        $total = array_sum($genreCounts) ?: 1;

        $result = [];
        foreach (array_slice($genreCounts, 0, 20, true) as $genre => $count) {
            $result[] = [
                'name' => $genre,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        return $result;
    }

    protected function computeTopArtists(): array
    {
        return DB::table('listening_stats')
            ->where('user_id', $this->userId)
            ->select('artist_name', DB::raw('COUNT(*) as play_count'))
            ->groupBy('artist_name')
            ->orderBy('play_count', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->artist_name,
                'play_count' => $row->play_count,
            ])
            ->toArray();
    }

    protected function computeTopTracks(): array
    {
        return DB::table('listening_stats')
            ->where('user_id', $this->userId)
            ->select('track_name', 'artist_name', DB::raw('COUNT(*) as play_count'))
            ->groupBy('track_name', 'artist_name')
            ->orderBy('play_count', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'track_name' => $row->track_name,
                'artist_name' => $row->artist_name,
                'play_count' => $row->play_count,
            ])
            ->toArray();
    }

    protected function checkAchievements(ListeningStat $stat): void
    {
        $achievementConfig = config('music.achievements', []);
        if (empty($achievementConfig)) {
            return;
        }

        $profile = MusicTasteProfile::where('user_id', $this->userId)->first();
        if (!$profile) {
            return;
        }

        $connectedSources = MusicSource::where('user_id', $this->userId)->where('is_connected', true)->count();

        $checks = [
            'first_scrobble' => $profile->total_plays,
            'hundred_plays' => $profile->total_plays,
            'thousand_plays' => $profile->total_plays,
            'ten_thousand_plays' => $profile->total_plays,
            'week_streak' => $profile->listening_streak,
            'month_streak' => $profile->listening_streak,
            'year_streak' => $profile->listening_streak,
            'five_sources' => $connectedSources,
        ];

        foreach ($checks as $achievementKey => $currentProgress) {
            if (!isset($achievementConfig[$achievementKey])) {
                continue;
            }

            $existing = MusicAchievement::where('user_id', $this->userId)
                ->where('achievement_key', $achievementKey)
                ->first();

            $config = $achievementConfig[$achievementKey];
            $threshold = $config['threshold'] ?? 0;

            if ($existing && $existing->is_unlocked) {
                continue;
            }

            if ($currentProgress >= $threshold) {
                if ($existing) {
                    $existing->update([
                        'progress' => $currentProgress,
                        'is_unlocked' => true,
                        'unlocked_at' => now(),
                    ]);
                } else {
                    MusicAchievement::create([
                        'user_id' => $this->userId,
                        'achievement_key' => $achievementKey,
                        'achievement_name' => $config['name'] ?? $achievementKey,
                        'description' => $config['description'] ?? '',
                        'threshold' => $threshold,
                        'progress' => $currentProgress,
                        'is_unlocked' => true,
                        'unlocked_at' => now(),
                    ]);
                }

                Log::info('ProcessScrobbleEvent: achievement unlocked', [
                    'user_id' => $this->userId,
                    'achievement' => $achievementKey,
                ]);
            } else {
                if ($existing) {
                    $existing->update(['progress' => $currentProgress]);
                } else {
                    MusicAchievement::create([
                        'user_id' => $this->userId,
                        'achievement_key' => $achievementKey,
                        'achievement_name' => $config['name'] ?? $achievementKey,
                        'description' => $config['description'] ?? '',
                        'threshold' => $threshold,
                        'progress' => $currentProgress,
                        'is_unlocked' => false,
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        MusicWebhookEvent::where('id', $this->webhookEventId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('ProcessScrobbleEvent: job failed after max attempts', [
            'webhook_event_id' => $this->webhookEventId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
