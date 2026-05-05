<?php

namespace Database\Seeders;

use App\Models\ListeningStat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ListeningStatSeeder extends Seeder
{
    protected array $sampleTracks = [
        ['track' => 'Bohemian Rhapsody', 'artist' => 'Queen', 'album' => 'A Night at the Opera', 'duration' => 354],
        ['track' => 'Stairway to Heaven', 'artist' => 'Led Zeppelin', 'album' => 'Led Zeppelin IV', 'duration' => 482],
        ['track' => 'Hotel California', 'artist' => 'Eagles', 'album' => 'Hotel California', 'duration' => 391],
        ['track' => 'Smells Like Teen Spirit', 'artist' => 'Nirvana', 'album' => 'Nevermind', 'duration' => 301],
        ['track' => 'Imagine', 'artist' => 'John Lennon', 'album' => 'Imagine', 'duration' => 183],
        ['track' => 'Like a Rolling Stone', 'artist' => 'Bob Dylan', 'album' => 'Highway 61 Revisited', 'duration' => 369],
        ['track' => 'Superstition', 'artist' => 'Stevie Wonder', 'album' => 'Talking Book', 'duration' => 245],
        ['track' => 'Purple Rain', 'artist' => 'Prince', 'album' => 'Purple Rain', 'duration' => 523],
        ['track' => 'Billie Jean', 'artist' => 'Michael Jackson', 'album' => 'Thriller', 'duration' => 294],
        ['track' => 'Hey Jude', 'artist' => 'The Beatles', 'album' => 'Single', 'duration' => 431],
        ['track' => 'Lose Yourself', 'artist' => 'Eminem', 'album' => '8 Mile Soundtrack', 'duration' => 326],
        ['track' => 'Creep', 'artist' => 'Radiohead', 'album' => 'Pablo Honey', 'duration' => 238],
        ['track' => 'Wonderwall', 'artist' => 'Oasis', 'album' => "What's the Story Morning Glory", 'duration' => 258],
        ['track' => 'No Rain', 'artist' => 'Blind Melon', 'album' => 'Blind Melon', 'duration' => 214],
        ['track' => 'Black Hole Sun', 'artist' => 'Soundgarden', 'album' => 'Superunknown', 'duration' => 320],
    ];

    protected array $sources = ['spotify', 'lastfm', 'listenbrainz', 'jellyfin', 'plex'];

    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $this->command->warn('No users found. Run user seeder first.');
            return;
        }

        $this->command->info('Seeding listening statistics...');

        foreach ($this->sampleTracks as $track) {
            $playCount = rand(1, 15);
            $source = $this->sources[array_rand($this->sources)];
            $daysAgo = rand(0, 60);

            for ($i = 0; $i < $playCount; $i++) {
                ListeningStat::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'source' => $source,
                    'track_name' => $track['track'],
                    'artist' => $track['artist'],
                    'album' => $track['album'],
                    'played_at' => now()->subDays($daysAgo)->subMinutes(rand(0, 1440)),
                    'duration' => $track['duration'],
                    'play_count' => 1,
                    'source_type' => $this->getSourceType($source),
                ]);
            }
        }

        $count = ListeningStat::count();
        $this->command->info("Seeded {$count} listening statistics.");
    }

    protected function getSourceType(string $source): string
    {
        return match ($source) {
            'spotify' => 'streaming',
            'lastfm' => 'scrobble',
            'listenbrainz' => 'scrobble',
            'jellyfin' => 'media_server',
            'plex' => 'media_server',
            default => 'unknown',
        };
    }
}
