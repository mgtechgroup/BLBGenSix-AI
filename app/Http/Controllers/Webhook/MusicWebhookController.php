<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScrobbleEvent;
use App\Models\MusicWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MusicWebhookController extends Controller
{
    protected const EVENT_TYPES = [
        'scrobble.new',
        'scrobble.now_playing',
        'source.connected',
        'source.disconnected',
        'source.sync.started',
        'source.sync.completed',
        'source.sync.failed',
        'error',
    ];

    public function handle(Request $request): JsonResponse
    {
        if (config('music.webhook.validate_signature') && config('music.webhook.secret')) {
            if (!$this->validateSignature($request)) {
                Log::warning('music.webhook.invalid_signature', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $eventType = data_get($payload, 'event_type') ?? data_get($payload, 'type') ?? '';
        $userId = data_get($payload, 'user_id') ?? data_get($payload, 'userId') ?? null;

        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            Log::warning('music.webhook.unknown_event_type', [
                'event_type' => $eventType,
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json(['error' => 'Unknown event type'], 400);
        }

        $webhookEvent = MusicWebhookEvent::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'source_type' => data_get($payload, 'source') ?? data_get($payload, 'source_type') ?? null,
            'payload' => $payload,
            'signature' => $request->header(config('music.webhook.signature_header', 'X-Webhook-Signature')),
            'status' => 'pending',
        ]);

        match ($eventType) {
            'scrobble.new', 'scrobble.now_playing' => $this->handleScrobble($webhookEvent, $payload),
            'source.connected', 'source.disconnected' => $this->handleSourceChange($webhookEvent, $payload),
            'source.sync.started', 'source.sync.completed', 'source.sync.failed' => $this->handleSync($webhookEvent, $payload),
            'error' => $this->handleError($webhookEvent, $payload),
            default => null,
        };

        return response()->json(['status' => 'accepted', 'event_id' => $webhookEvent->id], 200);
    }

    protected function handleScrobble(MusicWebhookEvent $event, array $payload): void
    {
        $scrobbleData = data_get($payload, 'scrobble') ?? data_get($payload, 'track') ?? data_get($payload, 'data') ?? [];

        if (empty($scrobbleData)) {
            $event->update(['status' => 'failed', 'error_message' => 'Missing scrobble data']);
            return;
        }

        $playedAt = data_get($scrobbleData, 'played_at') ?? data_get($scrobbleData, 'timestamp') ?? data_get($scrobbleData, 'date') ?? now();

        if (is_numeric($playedAt)) {
            $playedAt = now()->setTimestamp((int) $playedAt);
        } else {
            $playedAt = \Carbon\Carbon::parse($playedAt);
        }

        $trackId = hash('sha256', json_encode([
            'user_id' => $event->user_id,
            'track_name' => data_get($scrobbleData, 'track_name') ?? data_get($scrobbleData, 'name') ?? '',
            'artist_name' => data_get($scrobbleData, 'artist_name') ?? data_get($scrobbleData, 'artist') ?? '',
            'played_at' => $playedAt->toIso8601String(),
            'source' => data_get($payload, 'source') ?? '',
        ]));

        ProcessScrobbleEvent::dispatch(
            $event->id,
            $event->user_id,
            $scrobbleData,
            $playedAt,
            $trackId,
            $event->event_type
        )->onQueue(config('music.queue.queue', 'music-scrobbles'))
         ->onConnection(config('music.queue.connection', 'redis'))
         ->backoff(config('music.queue.backoff', [10, 30, 60, 180, 600]))
         ->delay(data_get($scrobbleData, 'delay_seconds') ? now()->addSeconds((int) data_get($scrobbleData, 'delay_seconds')) : null);
    }

    protected function handleSourceChange(MusicWebhookEvent $event, array $payload): void
    {
        $event->update(['status' => 'processed', 'processed_at' => now()]);

        Log::info('music.webhook.source_change', [
            'event_type' => $event->event_type,
            'source_type' => data_get($payload, 'source') ?? data_get($payload, 'source_type'),
            'user_id' => $event->user_id,
        ]);
    }

    protected function handleSync(MusicWebhookEvent $event, array $payload): void
    {
        $event->update(['status' => 'processed', 'processed_at' => now()]);

        if ($event->event_type === 'source.sync.failed') {
            Log::error('music.webhook.sync_failed', [
                'user_id' => $event->user_id,
                'source' => data_get($payload, 'source'),
                'error' => data_get($payload, 'error') ?? data_get($payload, 'message'),
            ]);
        }
    }

    protected function handleError(MusicWebhookEvent $event, array $payload): void
    {
        $event->update([
            'status' => 'failed',
            'error_message' => data_get($payload, 'error') ?? data_get($payload, 'message') ?? 'Unknown error',
        ]);

        Log::error('music.webhook.error_event', [
            'user_id' => $event->user_id,
            'source' => data_get($payload, 'source'),
            'error' => data_get($payload, 'error'),
        ]);
    }

    protected function validateSignature(Request $request): bool
    {
        $signature = $request->header(config('music.webhook.signature_header', 'X-Webhook-Signature'));
        $secret = config('music.webhook.secret');

        if (!$signature || !$secret) {
            return false;
        }

        $payload = $request->getContent();

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
