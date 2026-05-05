<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listening_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source')->index();
            $table->string('track_name')->index();
            $table->string('artist')->index();
            $table->string('album')->nullable();
            $table->timestamp('played_at')->index();
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->integer('play_count')->default(1);
            $table->string('track_mbid')->nullable();
            $table->string('artist_mbid')->nullable();
            $table->string('album_mbid')->nullable();
            $table->string('source_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'played_at']);
            $table->index(['user_id', 'artist']);
            $table->index(['user_id', 'track_name', 'artist']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listening_stats');
    }
};
