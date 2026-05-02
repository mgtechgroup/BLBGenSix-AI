<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('wallet_type');
            $table->string('wallet_model')->nullable();
            $table->string('network');
            $table->string('address');
            $table->binary('public_key')->nullable();
            $table->binary('address_signature_proof')->nullable();
            $table->boolean('is_cold_wallet')->default(false);
            $table->boolean('is_device_bound')->default(false);
            $table->string('binding_device_fingerprint')->nullable();
            $table->string('binding_passkey_credential_id')->nullable();
            $table->string('verification_method');
            $table->decimal('verification_score', 3, 2)->default(0);
            $table->string('status')->default('pending_verification');
            $table->timestamp('last_used_at')->nullable();
            $table->decimal('total_deposits', 18, 8)->default(0);
            $table->decimal('total_withdrawals', 18, 8)->default(0);
            $table->binary('metadata')->nullable();
            $table->timestamps();

            $table->index('wallet_type');
            $table->index('is_cold_wallet');
            $table->index('is_device_bound');
            $table->index('status');
            $table->index('address');
            $table->index('network');
            $table->unique(['user_id', 'address', 'network']);
        });

        Schema::create('crypto_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('crypto_wallets')->nullOnDelete();
            $table->string('payment_id')->unique();
            $table->string('network');
            $table->string('token');
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('amount_crypto', 18, 8);
            $table->decimal('exchange_rate', 18, 8);
            $table->string('sender_address')->nullable();
            $table->string('recipient_address');
            $table->string('order_type');
            $table->string('order_id');
            $table->string('tx_hash')->nullable();
            $table->integer('confirmations')->default(0);
            $table->string('status')->default('awaiting_payment');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('status');
            $table->index('tx_hash');
            $table->index('network');
        });

        Schema::create('crypto_withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('crypto_wallets')->cascadeOnDelete();
            $table->string('network');
            $table->string('token');
            $table->decimal('amount', 18, 8);
            $table->decimal('amount_usd', 12, 2);
            $table->string('destination_address');
            $table->string('tx_hash')->nullable();
            $table->string('hardware_signature_hash');
            $table->string('status')->default('pending');
            $table->integer('confirmations')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tx_hash');
        });

        // Add zero-knowledge fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('zk_public_key')->nullable()->after('remember_token');
            $table->binary('zk_encrypted_secret_key')->nullable()->after('zk_public_key');
            $table->string('zk_salt')->nullable()->after('zk_encrypted_secret_key');
            $table->string('biometric_challenge_token')->nullable()->after('zk_salt');
            $table->timestamp('last_biometric_at')->nullable()->after('biometric_challenge_token');
            $table->string('security_level')->default('zero_trust')->after('last_biometric_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['zk_public_key', 'zk_encrypted_secret_key', 'zk_salt', 'biometric_challenge_token', 'last_biometric_at', 'security_level']);
        });

        Schema::dropIfExists('crypto_withdrawals');
        Schema::dropIfExists('crypto_payments');
        Schema::dropIfExists('crypto_wallets');
    }
};
