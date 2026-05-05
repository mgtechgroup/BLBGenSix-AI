<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListeningStat;
use App\Services\MusicScrobbleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MusicController extends Controller
{
    protected MusicScrobbleService $scrobbleService;

    public function __construct(MusicScrobbleService $scrobbleService)
    {
        $this->scrobbleService = $scrobbleService;
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $source = $request->query('source');

        $query = ListeningStat::forUser($user->id);

        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        if ($source) {
            $query->source($source);
        }

        $totalPlays = (clone $query)->count();
        $totalDuration = (clone $query)->sum('duration') ?? 0;
        $uniqueTracks = (clone $query)->distinct('track_name', 'artist')->count('track_name');
        $uniqueArtists = (clone $query)->distinct('artist')->count('artist');
        $uniqueAlbums = (clone $query)->whereNotNull('album')->distinct('album')->count('album');

        $playsBySource = ListeningStat::forUser($user->id)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn($row) => [$row->source => (int) $row->count]);

        $recentPlays = ListeningStat::forUser($user->id)
            ->with('user:id,username')
            ->latest('played_at')
            ->limit(5)
            ->get()
            ->map(fn($stat) => [
                'track_name' => $stat->track_name,
                'artist' => $stat->artist,
                'album' => $stat->album,
                'played_at' => $stat->played_at?->toIso8601String(),
                'duration' => $stat->duration,
                'source' => $stat->source,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_plays' => $totalPlays,
                    'total_duration_seconds' => $totalDuration,
                    'total_duration_formatted' => $this->formatDuration($totalDuration),
                    'unique_tracks' => $uniqueTracks,
                    'unique_artists' => $uniqueArtists,
                    'unique_albums' => $uniqueAlbums,
                ],
                'plays_by_source' => $playsBySource,
                'recent_plays' => $recentPlays,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 20), 100);
        $source = $request->query('source');

        $livePlays = $this->scrobbleService->getRecentPlays($limit, $source);

        $localPlays = ListeningStat::forUser($user->id)
            ->when($source, fn($q) => $q->source($source))
            ->latest('played_at')
            ->limit($limit)
            ->get()
            ->map(fn($stat) => [
                'id' => $stat->id,
                'track_name' => $stat->track_name,
                'artist' => $stat->artist,
                'album' => $stat->album,
                'played_at' => $stat->played_at?->toIso8601String(),
                'duration' => $stat->duration,
                'source' => $stat->source,
                'play_count' => $stat->play_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'live' => $livePlays,
                'local' => $localPlays,
                'limit' => $limit,
                'source_filter' => $source,
            ],
        ]);
    }

    public function topArtists(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 10), 50);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $artists = ListeningStat::forUser($user->id)
            ->topArtists($limit, $startDate, $endDate)
            ->get()
            ->map(fn($row) => [
                'artist' => $row->artist,
                'play_count' => (int) $row->play_count,
                'total_duration' => (int) $row->total_duration,
                'total_duration_formatted' => $this->formatDuration($row->total_duration),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'artists' => $artists,
                'limit' => $limit,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
        ]);
    }

    public function topTracks(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 10), 50);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $tracks = ListeningStat::forUser($user->id)
            ->topTracks($limit, $startDate, $endDate)
            ->get()
            ->map(fn($row) => [
                'track_name' => $row->track_name,
                'artist' => $row->artist,
                'play_count' => (int) $row->play_count,
                'total_duration' => (int) $row->total_duration,
                'total_duration_formatted' => $this->formatDuration($row->total_duration),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'tracks' => $tracks,
                'limit' => $limit,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
        ]);
    }

    public function sources(Request $request): JsonResponse
    {
        $sourceHealth = $this->scrobbleService->getSourceHealth();

        $sourceStatuses = [];

        foreach ($sourceHealth as $source) {
            $playCount = ListeningStat::where('source', $source['id'] ?? $source['name'])
                ->count();

            $lastPlay = ListeningStat::where('source', $source['id'] ?? $source['name'])
                ->latest('played_at')
                ->first();

            $sourceStatuses[] = [
                'id' => $source['id'] ?? null,
                'name' => $source['name'],
                'type' => $source['type'],
                'status' => $source['status'],
                'connected' => $source['connected'],
                'auth_valid' => $source['auth_valid'],
                'local_play_count' => $playCount,
                'last_play_at' => $lastPlay?->played_at?->toIso8601String(),
                'scrobble_count' => $source['scrobble_count'] ?? 0,
                'error' => $source['error'],
                'updated_at' => $source['updated_at'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sources' => $sourceStatuses,
                'total_connected' => collect($sourceStatuses)->where('connected', true)->count(),
                'total_sources' => count($sourceStatuses),
            ],
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string', 'in:spotify,lastfm,listenbrainz,jellyfin,plex'],
            'code' => ['sometimes', 'string'],
            'state' => ['sometimes', 'string'],
            'token' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        $provider = $request->input('provider');

        Log::info('Music source connection initiated', [
            'user_id' => $user->id,
            'provider' => $provider,
        ]);

        try {
            $status = $this->scrobbleService->getStatus();

            if ($status['status'] === 'error') {
                return response()->json([
                    'success' => false,
                    'message' => 'Multi-scrobbler service is unavailable.',
                    'service_status' => $status,
                ], Response::HTTP_BAD_GATEWAY);
            }

            $callbackUrl = config('app.url') . "/api/v1/music/connect?provider={$provider}";

            return response()->json([
                'success' => true,
                'data' => [
                    'provider' => $provider,
                    'auth_url' => "{$this->scrobbleService->baseUrl}/auth/{$provider}?redirect_uri=" . urlencode($callbackUrl),
                    'callback_url' => $callbackUrl,
                    'message' => "Redirect user to auth_url to connect {$provider}.",
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Music connection failed', [
                'user_id' => $user->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to initiate connection to {$provider}.",
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $webhookSecret = config('services.multi_scrobbler.webhook_secret', env('LARAVEL_WEBHOOK_SECRET'));

        if ($webhookSecret) {
            $providedSecret = $request->header('X-Webhook-Secret');

            if ($providedSecret !== $webhookSecret) {
                Log::warning('Invalid webhook secret received', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        $validator = Validator::make($request->all(), [
            'event' => ['required', 'string'],
            'data' => ['required', 'array'],
            'data.track_name' => ['required', 'string'],
            'data.artist_name' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'play.scrobble') {
            $this->processScrobbleWebhook($data);
        }

        Log::info('Music webhook processed', [
            'event' => $event,
            'track' => $data['track_name'],
            'artist' => $data['artist_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received.',
            'event' => $event,
        ]);
    }

    protected function processScrobbleWebhook(array $data): void
    {
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            $users = \App\Models\User::limit(1)->get();
            if ($users->isEmpty()) {
                return;
            }
            $userId = $users->first()->id;
        }

        ListeningStat::recordPlay([
            'user_id' => $userId,
            'source' => $data['source'] ?? 'webhook',
            'track_name' => $data['track_name'],
            'artist' => $data['artist_name'],
            'album' => $data['album_name'] ?? null,
            'played_at' => $data['played_at'] ?? now(),
            'duration' => $data['duration'] ?? null,
            'track_mbid' => $data['track_mbid'] ?? null,
            'artist_mbid' => $data['artist_mbid'] ?? null,
            'album_mbid' => $data['album_mbid'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'metadata' => [
                'play_type' => $data['play_type'] ?? 'scrobble',
                'is_loved' => $data['is_loved'] ?? false,
            ],
        ]);

        $this->scrobbleService->invalidateCache();
    }

    protected function formatDuration(?int $seconds): string
    {
        if (!$seconds) {
            return '0m';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
