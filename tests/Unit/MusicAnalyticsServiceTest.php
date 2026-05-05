<?php

namespace Tests\Unit;

use App\Services\MusicAnalyticsService;
use App\Models\ListeningStat;
use App\Models\MusicTasteProfile;
use App\Models\MusicAchievement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MusicAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MusicAnalyticsService $service;
    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new MusicAnalyticsService();

        $user = \App\Models\User::factory()->create();
        $this->userId = $user->id;

        MusicTasteProfile::create([
            'user_id' => $this->userId,
            'total_plays' => 1000,
            'total_hours' => 50.5,
            'listening_streak' => 7,
            'last_listened_at' => now(),
            'avg_energy' => 0.65,
            'avg_valence' => 0.70,
            'avg_danceability' => 0.60,
            'avg_acousticness' => 0.40,
        ]);
    }

    public function test_get_listening_stats_returns_correct_structure(): void
    {
        $stats = $this->service->getListeningStats($this->userId, 'monthly');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('period_label', $stats);
        $this->assertArrayHasKey('total_plays', $stats);
        $this->assertArrayHasKey('unique_artists', $stats);
        $this->assertArrayHasKey('unique_tracks', $stats);
        $this->assertArrayHasKey('total_hours', $stats);
        $this->assertArrayHasKey('trend_percentage', $stats);
    }

    public function test_get_listening_stats_calculates_plays_correctly(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Artist 1',
            'source_type' => 'spotify',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 2',
            'artist_name' => 'Artist 2',
            'source_type' => 'spotify',
            'played_at' => now(),
            'duration_ms' => 240000,
        ]);

        $stats = $this->service->getListeningStats($this->userId, 'monthly');
        $this->assertEquals(2, $stats['total_plays']);
    }

    public function test_get_listening_stats_calculates_unique_counts(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Artist 1',
            'album_name' => 'Album 1',
            'source_type' => 'spotify',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 2',
            'artist_name' => 'Artist 1',
            'album_name' => 'Album 1',
            'source_type' => 'spotify',
            'played_at' => now(),
            'duration_ms' => 240000,
        ]);

        $stats = $this->service->getListeningStats($this->userId, 'monthly');
        $this->assertEquals(1, $stats['unique_artists']);
        $this->assertEquals(2, $stats['unique_tracks']);
        $this->assertEquals(1, $stats['unique_albums']);
    }

    public function test_get_top_artists_returns_correct_data(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Popular Artist',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 2',
            'artist_name' => 'Popular Artist',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 3',
            'artist_name' => 'Less Popular',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        $artists = $this->service->getTopArtists($this->userId, 10, 'monthly');

        $this->assertIsArray($artists);
        $this->assertCount(2, $artists);
        $this->assertEquals('Popular Artist', $artists[0]['name']);
        $this->assertEquals(2, $artists[0]['play_count']);
    }

    public function test_get_top_tracks_returns_correct_data(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Hit Song',
            'artist_name' => 'Artist',
            'album_name' => 'Album',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        $tracks = $this->service->getTopTracks($this->userId, 10, 'monthly');

        $this->assertIsArray($tracks);
        $this->assertCount(1, $tracks);
        $this->assertEquals('Hit Song', $tracks[0]['track_name']);
        $this->assertEquals('Artist', $tracks[0]['artist_name']);
    }

    public function test_get_top_albums_returns_correct_data(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Artist',
            'album_name' => 'Great Album',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        $albums = $this->service->getTopAlbums($this->userId, 10, 'monthly');

        $this->assertIsArray($albums);
        $this->assertCount(1, $albums);
        $this->assertEquals('Great Album', $albums[0]['album_name']);
    }

    public function test_get_top_genres_returns_parsed_genres(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Artist',
            'played_at' => now(),
            'genre_tags' => json_encode(['rock', 'alternative']),
        ]);

        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 2',
            'artist_name' => 'Artist',
            'played_at' => now(),
            'genre_tags' => json_encode(['rock', 'indie']),
        ]);

        $genres = $this->service->getTopGenres($this->userId, 10, 'monthly');

        $this->assertIsArray($genres);
        $this->assertGreaterThan(0, count($genres));
    }

    public function test_get_mood_analysis_detects_moods(): void
    {
        $moods = $this->service->getMoodAnalysis($this->userId, 'monthly');

        $this->assertIsArray($moods);
        $this->assertArrayHasKey('features', $moods);
        $this->assertArrayHasKey('moods', $moods);
        $this->assertArrayHasKey('primary_mood', $moods);
    }

    public function test_get_mood_analysis_returns_features(): void
    {
        $moods = $this->service->getMoodAnalysis($this->userId, 'monthly');

        $this->assertArrayHasKey('energy', $moods['features']);
        $this->assertArrayHasKey('valence', $moods['features']);
        $this->assertArrayHasKey('danceability', $moods['features']);
        $this->assertArrayHasKey('acousticness', $moods['features']);
    }

    public function test_get_listening_trends_returns_timeline(): void
    {
        $trends = $this->service->getListeningTrends($this->userId, 'monthly');

        $this->assertIsArray($trends);
        $this->assertArrayHasKey('period', $trends);
        $this->assertArrayHasKey('timeline', $trends);
        $this->assertArrayHasKey('hourly_distribution', $trends);
        $this->assertArrayHasKey('weekly_distribution', $trends);
    }

    public function test_get_recent_plays_returns_limited_results(): void
    {
        for ($i = 0; $i < 60; $i++) {
            ListeningStat::create([
                'user_id' => $this->userId,
                'track_name' => "Track {$i}",
                'artist_name' => 'Artist',
                'played_at' => now()->subMinutes($i),
                'duration_ms' => 180000,
            ]);
        }

        $recent = $this->service->getRecentPlays($this->userId, 50);
        $this->assertLessThanOrEqual(50, count($recent));
    }

    public function test_get_connected_sources_returns_empty_when_none(): void
    {
        $sources = $this->service->getConnectedSources($this->userId);
        $this->assertIsArray($sources);
        $this->assertCount(0, $sources);
    }

    public function test_get_connected_sources_returns_connected(): void
    {
        MusicSource::create([
            'user_id' => $this->userId,
            'source_type' => 'spotify',
            'external_username' => 'testuser',
            'is_connected' => true,
            'is_active' => true,
        ]);

        $sources = $this->service->getConnectedSources($this->userId);
        $this->assertCount(1, $sources);
        $this->assertEquals('spotify', $sources[0]['source_type']);
    }

    public function test_get_now_playing_returns_null_when_no_recent(): void
    {
        $nowPlaying = $this->service->getNowPlaying($this->userId);
        $this->assertNull($nowPlaying);
    }

    public function test_get_achievements_returns_user_achievements(): void
    {
        MusicAchievement::create([
            'user_id' => $this->userId,
            'achievement_key' => 'first_play',
            'achievement_name' => 'First Play',
            'description' => 'Play your first track',
            'threshold' => 1,
            'progress' => 1,
            'is_unlocked' => true,
            'unlocked_at' => now(),
        ]);

        $achievements = $this->service->getAchievements($this->userId);
        $this->assertIsArray($achievements);
        $this->assertCount(1, $achievements);
    }

    public function test_export_to_json_returns_complete_data(): void
    {
        $export = $this->service->exportToJson($this->userId, 'yearly');

        $this->assertIsArray($export);
        $this->assertArrayHasKey('exported_at', $export);
        $this->assertArrayHasKey('user_id', $export);
        $this->assertArrayHasKey('listening_stats', $export);
        $this->assertArrayHasKey('top_genres', $export);
        $this->assertArrayHasKey('top_artists', $export);
        $this->assertArrayHasKey('top_tracks', $export);
        $this->assertArrayHasKey('mood_analysis', $export);
    }

    public function test_export_to_csv_returns_string(): void
    {
        ListeningStat::create([
            'user_id' => $this->userId,
            'track_name' => 'Track 1',
            'artist_name' => 'Artist',
            'played_at' => now(),
            'duration_ms' => 180000,
        ]);

        $csv = $this->service->exportToCsv($this->userId, 'yearly');

        $this->assertIsString($csv);
        $this->assertStringContainsString('track_name', $csv);
        $this->assertStringContainsString('Track 1', $csv);
    }

    public function test_invalidate_cache_clears_cached_data(): void
    {
        Cache::put("music.stats.{$this->userId}.monthly", ['test' => 'data'], 3600);

        $this->service->invalidateCache($this->userId);

        $this->assertNull(Cache::get("music.stats.{$this->userId}.monthly"));
    }

    public function test_different_periods_return_different_stats(): void
    {
        $daily = $this->service->getListeningStats($this->userId, 'daily');
        $monthly = $this->service->getListeningStats($this->userId, 'monthly');
        $yearly = $this->service->getListeningStats($this->userId, 'yearly');

        $this->assertEquals('daily', $daily['period']);
        $this->assertEquals('monthly', $monthly['period']);
        $this->assertEquals('yearly', $yearly['period']);
    }
}
