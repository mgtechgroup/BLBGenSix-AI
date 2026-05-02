<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->date('date_of_birth');
            $table->boolean('is_verified_adult')->default(false);
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('subscription_status')->default('none');
            $table->string('subscription_plan')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->integer('api_usage_count')->default(0);
            $table->integer('api_usage_limit')->default(0);
            $table->integer('credits_remaining')->default(0);
            $table->boolean('is_banned')->default(false);
            $table->string('ban_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_verified_adult');
            $table->index('subscription_status');
            $table->index('is_banned');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id')->unique();
            $table->string('fingerprint')->unique();
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('os_version')->nullable();
            $table->string('last_ip')->nullable();
            $table->text('last_user_agent')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('biometric_enabled')->default(false);
            $table->string('biometric_type')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('fingerprint');
            $table->index('is_trusted');
        });

        Schema::create('verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('document_type')->nullable();
            $table->string('document_url')->nullable();
            $table->string('document_verified_url')->nullable();
            $table->binary('biometric_data')->nullable();
            $table->float('liveness_score')->nullable();
            $table->boolean('age_verified')->default(false);
            $table->string('verification_provider')->nullable();
            $table->json('provider_response')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('age_verified');
        });

        Schema::create('webauthn_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('credential_id')->unique();
            $table->string('type');
            $table->json('transports');
            $table->string('attestation_type');
            $table->string('trust_path')->nullable();
            $table->string('aaguid')->nullable();
            $table->binary('public_key');
            $table->unsignedBigInteger('counter')->default(0);
            $table->json('other_ui')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_keys');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
