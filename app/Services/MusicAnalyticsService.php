<?php

namespace App\Services;

use App\Models\ListeningStat;
use App\Models\MusicSource;
use App\Models\MusicTasteProfile;
use App\Models\MusicAchievement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MusicAnalyticsService
{
    protected const PERIODS = ['daily', 'weekly', 'monthly', 'yearly'];

    protected array $periodIntervals = [
        'daily' => [
            'group_format' => '%Y-%m-%d',
            'label' => 'Today',
            'range_days' => 1,
            'history_days' => 30,
        ],
        'weekly' => [
            'group_format' => '%Y-%u',
            'label' => 'This Week',
            'range_days' => 7,
            'history_days' => 12,
        ],
        'monthly' => [
            'group_format' => '%Y-%m',
            'label' => 'This Month',
            'range_days' => 30,
            'history_days' => 12,
        ],
        'yearly' => [
            'group_format' => '%Y',
            'label' => 'This Year',
            'range_days' => 365,
            'history_days' => 5,
        ],
    ];

    public function getListeningStats(int $userId, string $period = 'monthly'): array
    {
        $cacheKey = "music.stats.{$userId}.{$period}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $period) {
            $profile = MusicTasteProfile::where('user_id', $userId)->first();

            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            $periodStats = ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->select([
                    DB::raw('COUNT(*) as total_plays'),
                    DB::raw('COUNT(DISTINCT artist_name) as unique_artists'),
                    DB::raw('COUNT(DISTINCT track_name) as unique_tracks'),
                    DB::raw('COUNT(DISTINCT album_name) as unique_albums'),
                    DB::raw('COUNT(DISTINCT source_type) as unique_sources'),
                    DB::raw('COALESCE(SUM(duration_ms), 0) as total_duration_ms'),
                    DB::raw('SUM(CASE WHEN is_loved = 1 THEN 1 ELSE 0 END) as loved_count'),
                ])
                ->first();

            $totalHours = round(($periodStats->total_duration_ms ?? 0) / 3600000, 2);
            $avgPlaysPerDay = $interval['range_days'] > 0 ? round($periodStats->total_plays / $interval['range_days'], 1) : 0;

            $previousStart = $startDate->copy()->subDays($interval['range_days']);
            $previousStats = ListeningStat::where('user_id', $userId)
                ->whereBetween('played_at', [$previousStart, $startDate])
                ->count();

            $trend = 0;
            if ($previousStats > 0) {
                $trend = round((($periodStats->total_plays - $previousStats) / $previousStats) * 100, 1);
            }

            return [
                'period' => $period,
                'period_label' => $interval['label'],
                'total_plays' => $periodStats->total_plays ?? 0,
                'unique_artists' => $periodStats->unique_artists ?? 0,
                'unique_tracks' => $periodStats->unique_tracks ?? 0,
                'unique_albums' => $periodStats->unique_albums ?? 0,
                'total_hours' => $totalHours,
                'loved_tracks' => $periodStats->loved_count ?? 0,
                'avg_plays_per_day' => $avgPlaysPerDay,
                'trend_percentage' => $trend,
                'total_plays_all_time' => $profile?->total_plays ?? 0,
                'total_hours_all_time' => $profile?->total_hours ?? 0,
                'listening_streak' => $profile?->listening_streak ?? 0,
                'last_listened' => $profile?->last_listened_at,
            ];
        });
    }

    public function getTopGenres(int $userId, int $limit = 10, string $period = 'monthly'): array
    {
        $cacheKey = "music.genres.{$userId}.{$period}.{$limit}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $limit, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            $genreRows = ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->whereNotNull('genre_tags')
                ->pluck('genre_tags');

            $genreCounts = [];

            foreach ($genreRows as $genreJson) {
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
            foreach (array_slice($genreCounts, 0, $limit, true) as $genre => $count) {
                $result[] = [
                    'name' => ucfirst($genre),
                    'slug' => $genre,
                    'count' => $count,
                    'percentage' => round(($count / $total) * 100, 1),
                ];
            }

            return $result;
        });
    }

    public function getMoodAnalysis(int $userId, string $period = 'monthly'): array
    {
        $cacheKey = "music.moods.{$userId}.{$period}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            $profile = MusicTasteProfile::where('user_id', $userId)->first();

            $trackFeatures = ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->whereNotNull('track_features')
                ->pluck('track_features');

            $featureAverages = [
                'energy' => 0,
                'valence' => 0,
                'danceability' => 0,
                'acousticness' => 0,
                'instrumentalness' => 0,
                'liveness' => 0,
                'speechiness' => 0,
            ];

            $featureCounts = [];

            foreach ($trackFeatures as $featureJson) {
                $features = is_string($featureJson) ? json_decode($featureJson, true) : [];
                if (!is_array($features)) {
                    continue;
                }

                foreach ($featureAverages as $key => $currentAvg) {
                    if (isset($features[$key]) && is_numeric($features[$key])) {
                        $featureCounts[$key] = ($featureCounts[$key] ?? 0) + 1;
                        $n = $featureCounts[$key];
                        $featureAverages[$key] = round((($currentAvg * ($n - 1)) + $features[$key]) / $n, 4);
                    }
                }
            }

            $moods = $this->detectMoods($featureAverages);

            return [
                'features' => $featureAverages,
                'profile_features' => [
                    'avg_energy' => $profile?->avg_energy,
                    'avg_valence' => $profile?->avg_valence,
                    'avg_danceability' => $profile?->avg_danceability,
                    'avg_acousticness' => $profile?->avg_acousticness,
                ],
                'moods' => $moods,
                'primary_mood' => !empty($moods) ? $moods[0]['name'] : 'Unknown',
                'sample_size' => array_sum($featureCounts) > 0 ? max($featureCounts) : 0,
            ];
        });
    }

    public function getListeningTrends(int $userId, string $period = 'monthly'): array
    {
        $cacheKey = "music.trends.{$userId}.{$period}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $historyDays = $interval['history_days'] * (
                $period === 'daily' ? 1 :
                $period === 'weekly' ? 7 :
                $period === 'monthly' ? 30 : 365
            );
            $startDate = now()->subDays($historyDays)->startOfDay();

            $format = match ($period) {
                'daily' => DB::raw("DATE(played_at) as period_key"),
                'weekly' => DB::raw("TO_CHAR(played_at, 'IYYY-IW') as period_key"),
                'monthly' => DB::raw("TO_CHAR(played_at, 'YYYY-MM') as period_key"),
                'yearly' => DB::raw("EXTRACT(YEAR FROM played_at)::text as period_key"),
                default => DB::raw("DATE(played_at) as period_key"),
            };

            $timelineData = ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->select([
                    $format,
                    DB::raw('COUNT(*) as plays'),
                    DB::raw('COUNT(DISTINCT artist_name) as unique_artists'),
                    DB::raw('COALESCE(SUM(duration_ms), 0) as total_duration_ms'),
                ])
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->get()
                ->map(function ($row) use ($period) {
                    return [
                        'period' => $row->period_key,
                        'plays' => (int) $row->plays,
                        'unique_artists' => (int) $row->unique_artists,
                        'hours_listened' => round($row->total_duration_ms / 3600000, 2),
                    ];
                });

            $hourlyDistribution = $this->getHourlyDistribution($userId, $historyDays);
            $weeklyDistribution = $this->getWeeklyDistribution($userId, $historyDays);

            return [
                'period' => $period,
                'timeline' => $timelineData,
                'hourly_distribution' => $hourlyDistribution,
                'weekly_distribution' => $weeklyDistribution,
                'peak_listening_hour' => !empty($hourlyDistribution) ? max($hourlyDistribution, fn ($a, $b) => $a['plays'] <=> $b['plays'])['hour'] : null,
                'peak_listening_day' => !empty($weeklyDistribution) ? max($weeklyDistribution, fn ($a, $b) => $a['plays'] <=> $b['plays'])['day'] : null,
            ];
        });
    }

    public function getTopArtists(int $userId, int $limit = 10, string $period = 'monthly'): array
    {
        $cacheKey = "music.top_artists.{$userId}.{$period}.{$limit}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $limit, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            return ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->select('artist_name', 'artist_mbid', DB::raw('COUNT(*) as play_count'))
                ->groupBy('artist_name', 'artist_mbid')
                ->orderBy('play_count', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->artist_name,
                    'mbid' => $row->artist_mbid,
                    'play_count' => (int) $row->play_count,
                ])
                ->toArray();
        });
    }

    public function getTopTracks(int $userId, int $limit = 10, string $period = 'monthly'): array
    {
        $cacheKey = "music.top_tracks.{$userId}.{$period}.{$limit}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $limit, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            return ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->select(
                    'track_name',
                    'artist_name',
                    'album_name',
                    'track_mbid',
                    'duration_ms',
                    DB::raw('COUNT(*) as play_count')
                )
                ->groupBy('track_name', 'artist_name', 'album_name', 'track_mbid', 'duration_ms')
                ->orderBy('play_count', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'track_name' => $row->track_name,
                    'artist_name' => $row->artist_name,
                    'album_name' => $row->album_name,
                    'mbid' => $row->track_mbid,
                    'duration_ms' => (int) ($row->duration_ms ?? 0),
                    'play_count' => (int) $row->play_count,
                ])
                ->toArray();
        });
    }

    public function getTopAlbums(int $userId, int $limit = 10, string $period = 'monthly'): array
    {
        $cacheKey = "music.top_albums.{$userId}.{$period}.{$limit}";
        $ttl = config('music.analytics.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $limit, $period) {
            $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['monthly'];
            $startDate = now()->subDays($interval['range_days'])->startOfDay();

            return ListeningStat::where('user_id', $userId)
                ->where('played_at', '>=', $startDate)
                ->whereNotNull('album_name')
                ->select('album_name', 'album_mbid', 'artist_name', DB::raw('COUNT(*) as play_count'))
                ->groupBy('album_name', 'album_mbid', 'artist_name')
                ->orderBy('play_count', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'album_name' => $row->album_name,
                    'artist_name' => $row->artist_name,
                    'mbid' => $row->album_mbid,
                    'play_count' => (int) $row->play_count,
                ])
                ->toArray();
        });
    }

    public function getRecentPlays(int $userId, int $limit = 50): array
    {
        return ListeningStat::where('user_id', $userId)
            ->orderBy('played_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($stat) => [
                'id' => $stat->id,
                'track_name' => $stat->track_name,
                'artist_name' => $stat->artist_name,
                'album_name' => $stat->album_name,
                'duration_ms' => $stat->duration_ms,
                'played_at' => $stat->played_at->toISOString(),
                'is_loved' => $stat->is_loved,
                'source_type' => $stat->source_type,
                'genre_tags' => $stat->genre_tags ? json_decode($stat->genre_tags, true) : [],
            ])
            ->toArray();
    }

    public function getConnectedSources(int $userId): array
    {
        return MusicSource::where('user_id', $userId)
            ->get()
            ->map(fn ($source) => [
                'id' => $source->id,
                'source_type' => $source->source_type,
                'source_label' => $source->source_label ?? $source->source_type,
                'external_username' => $source->external_username,
                'is_connected' => $source->is_connected,
                'is_active' => $source->is_active,
                'last_sync_at' => $source->last_sync_at?->toISOString(),
                'last_scrobble_at' => $source->last_scrobble_at?->toISOString(),
            ])
            ->toArray();
    }

    public function getNowPlaying(int $userId): ?array
    {
        $recent = ListeningStat::where('user_id', $userId)
            ->where('played_at', '>=', now()->subMinutes(10))
            ->orderBy('played_at', 'desc')
            ->first();

        if (!$recent) {
            return null;
        }

        $isPlaying = $recent->played_at->diffInSeconds(now()) < 300;

        return [
            'track_name' => $recent->track_name,
            'artist_name' => $recent->artist_name,
            'album_name' => $recent->album_name,
            'source_type' => $recent->source_type,
            'played_at' => $recent->played_at->toISOString(),
            'is_playing' => $isPlaying,
        ];
    }

    public function getAchievements(int $userId): array
    {
        return MusicAchievement::where('user_id', $userId)
            ->orderBy('unlocked_at', 'desc')
            ->orderBy('achievement_key')
            ->get()
            ->map(fn ($achievement) => [
                'id' => $achievement->id,
                'key' => $achievement->achievement_key,
                'name' => $achievement->achievement_name,
                'description' => $achievement->description,
                'threshold' => $achievement->threshold,
                'progress' => $achievement->progress,
                'is_unlocked' => $achievement->is_unlocked,
                'unlocked_at' => $achievement->unlocked_at?->toISOString(),
            ])
            ->toArray();
    }

    public function exportToJson(int $userId, string $period = 'yearly'): array
    {
        $stats = $this->getListeningStats($userId, $period);
        $genres = $this->getTopGenres($userId, 50, $period);
        $artists = $this->getTopArtists($userId, 50, $period);
        $tracks = $this->getTopTracks($userId, 50, $period);
        $albums = $this->getTopAlbums($userId, 50, $period);
        $trends = $this->getListeningTrends($userId, $period);
        $moods = $this->getMoodAnalysis($userId, $period);

        return [
            'exported_at' => now()->toISOString(),
            'user_id' => $userId,
            'period' => $period,
            'listening_stats' => $stats,
            'top_genres' => $genres,
            'top_artists' => $artists,
            'top_tracks' => $tracks,
            'top_albums' => $albums,
            'trends' => $trends,
            'mood_analysis' => $moods,
        ];
    }

    public function exportToCsv(int $userId, string $period = 'yearly'): string
    {
        $limit = config('music.analytics.export_limit', 10000);
        $interval = $this->periodIntervals[$period] ?? $this->periodIntervals['yearly'];
        $startDate = now()->subDays($interval['history_days'] * (
            $period === 'daily' ? 1 :
            $period === 'weekly' ? 7 :
            $period === 'monthly' ? 30 : 365
        ))->startOfDay();

        $stats = ListeningStat::where('user_id', $userId)
            ->where('played_at', '>=', $startDate)
            ->orderBy('played_at', 'desc')
            ->limit($limit)
            ->get();

        $csv = fopen('php://temp/maxmemory:5242880', 'r+');

        fputcsv($csv, [
            'played_at',
            'track_name',
            'artist_name',
            'album_name',
            'source',
            'duration_ms',
            'is_loved',
            'genre_tags',
        ]);

        foreach ($stats as $stat) {
            fputcsv($csv, [
                $stat->played_at->toISOString(),
                $stat->track_name,
                $stat->artist_name,
                $stat->album_name ?? '',
                $stat->source_type ?? '',
                $stat->duration_ms ?? '',
                $stat->is_loved ? 'yes' : 'no',
                $stat->genre_tags ? implode(';', json_decode($stat->genre_tags, true) ?? []) : '',
            ]);
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return $csvContent;
    }

    public function invalidateCache(int $userId): void
    {
        $patterns = [
            "music.stats.{$userId}.*",
            "music.genres.{$userId}.*",
            "music.moods.{$userId}.*",
            "music.trends.{$userId}.*",
            "music.top_artists.{$userId}.*",
            "music.top_tracks.{$userId}.*",
            "music.top_albums.{$userId}.*",
        ];

        foreach ($patterns as $pattern) {
            Cache::keys([$pattern])->each(fn ($key) => Cache::forget($key));
        }
    }

    protected function detectMoods(array $features): array
    {
        $moods = [];

        $energy = $features['energy'] ?? 0.5;
        $valence = $features['valence'] ?? 0.5;
        $danceability = $features['danceability'] ?? 0.5;
        $acousticness = $features['acousticness'] ?? 0.5;

        if ($energy > 0.7 && $valence > 0.6) {
            $moods[] = ['name' => 'Energetic', 'confidence' => round(min(1, ($energy + $valence) / 2), 2)];
        }

        if ($valence > 0.7 && $energy < 0.5) {
            $moods[] = ['name' => 'Happy', 'confidence' => round($valence * 0.8, 2)];
        }

        if ($valence < 0.3 && $energy < 0.4) {
            $moods[] = ['name' => 'Melancholic', 'confidence' => round((1 - $valence) * 0.7, 2)];
        }

        if ($energy < 0.4 && $acousticness > 0.6) {
            $moods[] = ['name' => 'Calm', 'confidence' => round(($acousticness + (1 - $energy)) / 2.5, 2)];
        }

        if ($danceability > 0.7 && $energy > 0.5) {
            $moods[] = ['name' => 'Danceable', 'confidence' => round(($danceability + $energy) / 2, 2)];
        }

        if ($energy > 0.6 && $valence < 0.4) {
            $moods[] = ['name' => 'Intense', 'confidence' => round($energy * 0.7, 2)];
        }

        if ($acousticness > 0.7) {
            $moods[] = ['name' => 'Acoustic', 'confidence' => round($acousticness, 2)];
        }

        if ($features['instrumentalness'] > 0.7) {
            $moods[] = ['name' => 'Instrumental', 'confidence' => round($features['instrumentalness'], 2)];
        }

        if ($features['liveness'] > 0.6) {
            $moods[] = ['name' => 'Live', 'confidence' => round($features['liveness'] * 0.8, 2)];
        }

        if (empty($moods)) {
            $moods[] = ['name' => 'Neutral', 'confidence' => 0.5];
        }

        usort($moods, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $moods;
    }

    protected function getHourlyDistribution(int $userId, int $historyDays): array
    {
        $distribution = array_fill(0, 24, ['hour' => null, 'plays' => 0]);

        $hourlyCounts = ListeningStat::where('user_id', $userId)
            ->where('played_at', '>=', now()->subDays($historyDays)->startOfDay())
            ->select(DB::raw('EXTRACT(HOUR FROM played_at) as hour'), DB::raw('COUNT(*) as plays'))
            ->groupBy('hour')
            ->get();

        foreach ($hourlyCounts as $row) {
            $hour = (int) $row->hour;
            $distribution[$hour] = ['hour' => $hour, 'plays' => (int) $row->plays];
        }

        return $distribution;
    }

    protected function getWeeklyDistribution(int $userId, int $historyDays): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $distribution = [];

        foreach ($days as $index => $day) {
            $distribution[] = ['day' => $day, 'day_index' => $index, 'plays' => 0];
        }

        $weeklyCounts = ListeningStat::where('user_id', $userId)
            ->where('played_at', '>=', now()->subDays($historyDays)->startOfDay())
            ->select(DB::raw('EXTRACT(DOW FROM played_at) as day_index'), DB::raw('COUNT(*) as plays'))
            ->groupBy('day_index')
            ->get();

        foreach ($weeklyCounts as $row) {
            $index = (int) $row->day_index;
            if (isset($distribution[$index])) {
                $distribution[$index]['plays'] = (int) $row->plays;
            }
        }

        return $distribution;
    }
}
