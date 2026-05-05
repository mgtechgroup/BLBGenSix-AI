<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_label')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_username')->nullable();
            $table->binary('access_token_encrypted')->nullable();
            $table->binary('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_scrobble_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'source_type']);
            $table->index('is_connected');
            $table->unique(['user_id', 'source_type', 'external_id']);
        });

        Schema::create('listening_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('music_source_id')->nullable()->constrained('music_sources')->nullOnDelete();
            $table->string('track_name');
            $table->string('artist_name');
            $table->string('album_name')->nullable();
            $table->string('track_mbid')->nullable();
            $table->string('artist_mbid')->nullable();
            $table->string('album_mbid')->nullable();
            $table->string('source_type')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('played_at');
            $table->boolean('is_loved')->default(false);
            $table->json('track_features')->nullable();
            $table->json('genre_tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'played_at']);
            $table->index(['user_id', 'artist_name']);
            $table->index(['user_id', 'track_mbid']);
            $table->index('source_type');
            $table->unique(['user_id', 'track_mbid', 'played_at', 'source_type'], 'listening_stats_unique_scrobble');
        });

        Schema::create('music_taste_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('top_genres')->nullable();
            $table->json('top_artists')->nullable();
            $table->json('top_tracks')->nullable();
            $table->json('mood_distribution')->nullable();
            $table->json('listening_patterns')->nullable();
            $table->float('avg_energy')->nullable();
            $table->float('avg_valence')->nullable();
            $table->float('avg_danceability')->nullable();
            $table->float('avg_acousticness')->nullable();
            $table->integer('total_plays')->default(0);
            $table->integer('total_hours')->default(0);
            $table->integer('listening_streak')->default(0);
            $table->timestamp('last_listened_at')->nullable();
            $table->timestamp('profile_updated_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('music_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('achievement_key');
            $table->string('achievement_name');
            $table->text('description')->nullable();
            $table->json('criteria')->nullable();
            $table->integer('threshold')->nullable();
            $table->integer('progress')->default(0);
            $table->boolean('is_unlocked')->default(false);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_unlocked']);
            $table->unique(['user_id', 'achievement_key']);
        });

        Schema::create('music_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('source_type')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_webhook_events');
        Schema::dropIfExists('music_achievements');
        Schema::dropIfExists('music_taste_profiles');
        Schema::dropIfExists('listening_stats');
        Schema::dropIfExists('music_sources');
    }
};
