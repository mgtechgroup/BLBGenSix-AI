<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ListeningStat extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'source',
        'track_name',
        'artist',
        'album',
        'played_at',
        'duration',
        'play_count',
        'track_mbid',
        'artist_mbid',
        'album_mbid',
        'source_type',
        'metadata',
    ];

    protected $casts = [
        'played_at' => 'datetime',
        'duration' => 'integer',
        'play_count' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDateRange(Builder $query, ?string $start = null, ?string $end = null): Builder
    {
        if ($start) {
            $query->where('played_at', '>=', Carbon::parse($start));
        }

        if ($end) {
            $query->where('played_at', '<=', Carbon::parse($end)->endOfDay());
        }

        return $query;
    }

    public function scopeSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeTopArtists(Builder $query, int $limit = 10, ?string $startDate = null, ?string $endDate = null): Builder
    {
        $query->selectRaw('artist, COUNT(*) as play_count, SUM(duration) as total_duration')
            ->groupBy('artist')
            ->orderByDesc('play_count')
            ->limit($limit);

        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query;
    }

    public function scopeTopTracks(Builder $query, int $limit = 10, ?string $startDate = null, ?string $endDate = null): Builder
    {
        $query->selectRaw('track_name, artist, COUNT(*) as play_count, SUM(duration) as total_duration')
            ->groupBy('track_name', 'artist')
            ->orderByDesc('play_count')
            ->limit($limit);

        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query;
    }

    public function scopeTopAlbums(Builder $query, int $limit = 10, ?string $startDate = null, ?string $endDate = null): Builder
    {
        $query->selectRaw('album, artist, COUNT(*) as play_count')
            ->whereNotNull('album')
            ->groupBy('album', 'artist')
            ->orderByDesc('play_count')
            ->limit($limit);

        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query;
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public static function recordPlay(array $data): self
    {
        $existing = self::where('user_id', $data['user_id'])
            ->where('track_name', $data['track_name'])
            ->where('artist', $data['artist'])
            ->whereDate('played_at', Carbon::parse($data['played_at'])->toDateString())
            ->first();

        if ($existing) {
            $existing->increment('play_count');
            return $existing;
        }

        return self::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $data['user_id'],
            'source' => $data['source'] ?? 'unknown',
            'track_name' => $data['track_name'],
            'artist' => $data['artist'],
            'album' => $data['album'] ?? null,
            'played_at' => $data['played_at'],
            'duration' => $data['duration'] ?? null,
            'play_count' => 1,
            'track_mbid' => $data['track_mbid'] ?? null,
            'artist_mbid' => $data['artist_mbid'] ?? null,
            'album_mbid' => $data['album_mbid'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }
}
