<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FeatureFlag;
use App\Models\ListeningStat;
use App\Models\MusicSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create([
            'subscription_plan' => 'pro',
            'is_verified_adult' => true,
            'is_banned' => false,
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_health_endpoint_returns_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200)
            ->assertJson(['status' => 'healthy'])
            ->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_plans_endpoint_returns_available_plans(): void
    {
        $response = $this->getJson('/api/v1/plans');
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['name', 'price', 'features']]]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/music/stats');
        $response->assertStatus(401);
    }

    public function test_authenticated_request_succeeds(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/stats');
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_music_stats_endpoint_returns_correct_structure(): void
    {
        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'Test Track',
            'artist' => 'Test Artist',
            'source' => 'spotify',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => ['total_plays', 'total_duration_seconds', 'unique_tracks', 'unique_artists'],
                    'plays_by_source',
                    'recent_plays',
                ],
            ]);
    }

    public function test_music_recent_endpoint_with_limit(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/recent?limit=5');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.limit', 5);
    }

    public function test_music_top_artists_endpoint(): void
    {
        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'Track 1',
            'artist' => 'Artist One',
            'source' => 'spotify',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/top-artists?limit=10');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['artists' => ['*' => ['artist', 'play_count']]]]);
    }

    public function test_music_top_tracks_endpoint(): void
    {
        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'Track One',
            'artist' => 'Artist One',
            'source' => 'spotify',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/top-tracks');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_music_sources_endpoint(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/sources');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['sources', 'total_connected', 'total_sources']]);
    }

    public function test_music_connect_endpoint_validates_provider(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/music/connect', ['provider' => 'invalid']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_music_connect_endpoint_with_valid_provider(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/music/connect', ['provider' => 'spotify']);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['provider', 'auth_url']]);
    }

    public function test_music_webhook_with_invalid_secret(): void
    {
        Config::set('services.multi_scrobbler.webhook_secret', 'valid-secret');

        $response = $this->postJson('/api/v1/music/webhook', [
            'event' => 'play.scrobble',
            'data' => ['track_name' => 'Test', 'artist_name' => 'Artist'],
        ], ['X-Webhook-Secret' => 'invalid-secret']);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_music_webhook_with_valid_secret(): void
    {
        Config::set('services.multi_scrobbler.webhook_secret', 'valid-secret');

        $response = $this->postJson('/api/v1/music/webhook', [
            'event' => 'play.scrobble',
            'data' => ['track_name' => 'Test Track', 'artist_name' => 'Test Artist'],
        ], ['X-Webhook-Secret' => 'valid-secret']);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'event' => 'play.scrobble']);
    }

    public function test_music_webhook_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/music/webhook', [
            'event' => 'play.scrobble',
            'data' => ['track_name' => 'Test'],
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_rate_limiting_on_api_endpoints(): void
    {
        Config::set('app.throttle.api', '5,1');

        for ($i = 0; $i < 6; $i++) {
            $response = $this->getJson('/api/v1/health');
        }

        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_feature_flag_gating_blocks_unauthorized_access(): void
    {
        $freeUser = User::factory()->create([
            'subscription_plan' => 'free',
            'is_verified_adult' => true,
            'is_banned' => false,
        ]);
        $freeToken = $freeUser->createToken('test')->plainTextToken;

        FeatureFlag::create([
            'name' => 'nft_minting',
            'enabled' => true,
            'min_plan' => 'enterprise',
            'category' => 'revenue',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $freeToken)
            ->getJson('/api/v1/feature-flags/for-user');

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.nft_minting.enabled'));
    }

    public function test_feature_flag_gating_allows_authorized_access(): void
    {
        FeatureFlag::create([
            'name' => 'music_analytics',
            'enabled' => true,
            'min_plan' => 'pro',
            'category' => 'music',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/feature-flags/for-user');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.music_analytics.enabled'));
    }

    public function test_listening_stats_date_range_filter(): void
    {
        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'Old Track',
            'artist' => 'Artist',
            'source' => 'spotify',
            'played_at' => now()->subMonths(2),
            'duration' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'New Track',
            'artist' => 'Artist',
            'source' => 'spotify',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/stats?' . http_build_query([
                'start_date' => now()->subDays(7)->toIso8601String(),
                'end_date' => now()->toIso8601String(),
            ]));

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.summary.total_plays'));
    }

    public function test_music_stats_source_filter(): void
    {
        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'Spotify Track',
            'artist' => 'Artist',
            'source' => 'spotify',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        ListeningStat::create([
            'user_id' => $this->user->id,
            'track_name' => 'LastFM Track',
            'artist' => 'Artist',
            'source' => 'lastfm',
            'played_at' => now(),
            'duration' => 180000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/music/stats?source=spotify');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.summary.total_plays'));
    }

    public function test_zero_trust_banned_user_blocked(): void
    {
        $bannedUser = User::factory()->create([
            'is_banned' => true,
            'ban_reason' => 'Test ban',
        ]);
        $bannedToken = $bannedUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $bannedToken)
            ->withHeader('User-Agent', 'Test')
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Account banned']);
    }

    public function test_zero_trust_unverified_adult_blocked(): void
    {
        $unverifiedUser = User::factory()->create([
            'is_verified_adult' => false,
        ]);
        $unverifiedToken = $unverifiedUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->withHeader('User-Agent', 'Test')
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Adult verification required']);
    }
}
