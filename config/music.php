<?php

return [

    'multi_scrobbler' => [
        'api_url' => env('MULTI_SCROBBLER_API_URL', 'http://localhost:9078'),
        'api_key' => env('MULTI_SCROBBLER_API_KEY'),
        'timeout' => 30,
    ],

    'webhook' => [
        'secret' => env('MUSIC_WEBHOOK_SECRET'),
        'validate_signature' => env('MUSIC_WEBHOOK_VALIDATE_SIGNATURE', true),
        'signature_header' => env('MUSIC_WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),
    ],

    'analytics' => [
        'cache_ttl' => env('MUSIC_ANALYTICS_CACHE_TTL', 3600),
        'export_limit' => 10000,
        'premium_min_plan' => 1,
    ],

    'sources' => [
        'spotify' => [
            'enabled' => true,
            'label' => 'Spotify',
            'icon' => 'spotify',
            'color' => '#1DB954',
            'oauth' => true,
            'help_text' => 'Connect your Spotify account to sync your listening history automatically. Requires a Spotify Premium or Free account.',
            'oauth_scopes' => ['user-read-private', 'user-read-email', 'user-top-read', 'user-read-recently-played'],
        ],
        'lastfm' => [
            'enabled' => true,
            'label' => 'Last.fm',
            'icon' => 'lastfm',
            'color' => '#D51007',
            'oauth' => true,
            'help_text' => 'Connect Last.fm to import your scrobble history. You will need your Last.fm API key from https://www.last.fm/api/account/create.',
        ],
        'listenbrainz' => [
            'enabled' => true,
            'label' => 'ListenBrainz',
            'icon' => 'listenbrainz',
            'color' => '#E74C3C',
            'oauth' => false,
            'help_text' => 'Connect via your ListenBrainz user token found at https://listenbrainz.org/profile/.',
        ],
        'jellyfin' => [
            'enabled' => true,
            'label' => 'Jellyfin',
            'icon' => 'jellyfin',
            'color' => '#00A4DC',
            'oauth' => false,
            'help_text' => 'Connect your self-hosted Jellyfin server. You will need the server URL and API key.',
        ],
        'plex' => [
            'enabled' => true,
            'label' => 'Plex',
            'icon' => 'plex',
            'color' => '#E5A00D',
            'oauth' => true,
            'help_text' => 'Connect your Plex Media Server to sync music playback history. Requires Plex Pass for full features.',
        ],
        'subsonic' => [
            'enabled' => false,
            'label' => 'Subsonic / Navidrome',
            'icon' => 'subsonic',
            'color' => '#4CAF50',
            'oauth' => false,
            'help_text' => 'Connect any Subsonic-compatible server including Navidrome, Airsonic, and Gonic.',
        ],
        'youtube_music' => [
            'enabled' => false,
            'label' => 'YouTube Music',
            'icon' => 'youtube',
            'color' => '#FF0000',
            'oauth' => true,
            'help_text' => 'Connect your YouTube Music account to sync listening history.',
        ],
        'apple_music' => [
            'enabled' => false,
            'label' => 'Apple Music',
            'icon' => 'apple',
            'color' => '#FA243C',
            'oauth' => true,
            'help_text' => 'Connect Apple Music via MusicKit. Requires an Apple Developer account.',
        ],
    ],

    'features' => [
        'dashboard' => [
            'free' => ['overview', 'recent_plays'],
            'starter' => ['overview', 'recent_plays', 'top_artists', 'top_tracks', 'streak'],
            'pro' => ['overview', 'recent_plays', 'top_artists', 'top_tracks', 'streak', 'genre_analysis', 'mood_analysis', 'trends', 'connected_sources'],
            'enterprise' => ['overview', 'recent_plays', 'top_artists', 'top_tracks', 'streak', 'genre_analysis', 'mood_analysis', 'trends', 'connected_sources', 'export', 'advanced_analytics'],
        ],
        'achievements' => [
            'min_plan' => 'free',
            'enabled' => true,
        ],
        'real_time' => [
            'min_plan' => 'starter',
            'enabled' => true,
        ],
    ],

    'queue' => [
        'connection' => env('MUSIC_QUEUE_CONNECTION', 'redis'),
        'queue' => 'music-scrobbles',
        'retry_after' => 300,
        'max_attempts' => 5,
        'backoff' => [10, 30, 60, 180, 600],
    ],

    'achievements' => [
        'first_scrobble' => [
            'name' => 'First Play',
            'description' => 'Scrobble your first track',
            'threshold' => 1,
        ],
        'hundred_plays' => [
            'name' => 'Century Listener',
            'description' => 'Scrobble 100 tracks',
            'threshold' => 100,
        ],
        'thousand_plays' => [
            'name' => 'Music Aficionado',
            'description' => 'Scrobble 1,000 tracks',
            'threshold' => 1000,
        ],
        'ten_thousand_plays' => [
            'name' => 'Hardcore Listener',
            'description' => 'Scrobble 10,000 tracks',
            'threshold' => 10000,
        ],
        'week_streak' => [
            'name' => 'Weekly Dedicated',
            'description' => 'Maintain a 7-day listening streak',
            'threshold' => 7,
        ],
        'month_streak' => [
            'name' => 'Monthly Devoted',
            'description' => 'Maintain a 30-day listening streak',
            'threshold' => 30,
        ],
        'year_streak' => [
            'name' => 'Year-Long Listener',
            'description' => 'Maintain a 365-day listening streak',
            'threshold' => 365,
        ],
        'five_sources' => [
            'name' => 'Source Collector',
            'description' => 'Connect 5 music sources',
            'threshold' => 5,
        ],
        'genre_explorer' => [
            'name' => 'Genre Explorer',
            'description' => 'Listen to tracks from 50 different genres',
            'threshold' => 50,
        ],
    ],

];
