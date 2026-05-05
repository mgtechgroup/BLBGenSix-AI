<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->string('min_plan')->default('free');
            $table->string('description')->nullable();
            $table->string('category')->default('general');
            $table->timestamps();

            $table->index('name');
            $table->index('enabled');
            $table->index('category');
        });

        $this->seedDefaultFeatures();
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }

    protected function seedDefaultFeatures(): void
    {
        $now = now()->toDateTimeString();

        $features = [
            // Core Features
            [
                'name'               => 'auth_biometric',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Biometric authentication (fingerprint, face, voice)',
                'category'           => 'core',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'auth_passkey',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'WebAuthn / FIDO2 passkey authentication',
                'category'           => 'core',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'auth_2fa',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Two-factor authentication via TOTP',
                'category'           => 'core',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],

            // Generation Features
            [
                'name'               => 'image_generation',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'AI image generation from text prompts',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'video_generation',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'AI video generation from text prompts',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'text_generation',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'AI text generation and chat completions',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'body_mapping',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'AI body mapping and pose generation',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'image_upscale',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'AI image upscaling and enhancement',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'batch_generation',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Batch generation of multiple outputs at once',
                'category'           => 'generation',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],

            // Revenue Features
            [
                'name'               => 'crypto_payments',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Cryptocurrency payment processing',
                'category'           => 'revenue',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'affiliate_program',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'Affiliate marketing and referral program',
                'category'           => 'revenue',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'ad_monetization',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Advertising monetization and revenue sharing',
                'category'           => 'revenue',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'nft_minting',
                'enabled'            => false,
                'rollout_percentage' => 0,
                'min_plan'           => 'enterprise',
                'description'        => 'NFT minting and blockchain asset creation',
                'category'           => 'revenue',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],

            // UI Features
            [
                'name'               => 'dark_mode',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Dark mode UI theme',
                'category'           => 'ui',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'admin_dashboard',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Administrative dashboard and controls',
                'category'           => 'ui',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'analytics_view',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'User-facing analytics and statistics',
                'category'           => 'ui',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'export_data',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Data export in CSV / JSON formats',
                'category'           => 'ui',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],

            // Experimental Features
            [
                'name'               => 'webui_search',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Search functionality in the web UI',
                'category'           => 'experimental',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'dht_search',
                'enabled'            => false,
                'rollout_percentage' => 0,
                'min_plan'           => 'enterprise',
                'description'        => 'Decentralized hash table content search',
                'category'           => 'experimental',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'ai_recommendations',
                'enabled'            => false,
                'rollout_percentage' => 0,
                'min_plan'           => 'enterprise',
                'description'        => 'AI-powered content recommendations',
                'category'           => 'experimental',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'live_collab',
                'enabled'            => false,
                'rollout_percentage' => 0,
                'min_plan'           => 'enterprise',
                'description'        => 'Real-time collaborative editing',
                'category'           => 'experimental',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],

            // Music Features
            [
                'name'               => 'music_dashboard',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'Music listening dashboard and statistics',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'music_connect',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'Connect music sources (Spotify, Last.fm, etc.)',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'music_analytics',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Advanced music analytics (genres, moods, trends)',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'music_export',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'pro',
                'description'        => 'Export music data as JSON/CSV',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'music_realtime',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'starter',
                'description'        => 'Real-time now playing via SSE',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'music_achievements',
                'enabled'            => true,
                'rollout_percentage' => 100,
                'min_plan'           => 'free',
                'description'        => 'Music listening achievements',
                'category'           => 'music',
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
        ];

        DB::table('feature_flags')->insert($features);
    }
};
