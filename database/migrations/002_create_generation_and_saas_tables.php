<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('model_used');
            $table->text('prompt');
            $table->text('negative_prompt')->nullable();
            $table->json('parameters');
            $table->string('status')->default('queued');
            $table->string('output_url')->nullable();
            $table->json('output_files')->nullable();
            $table->float('processing_time')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('user_id');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('plan');
            $table->string('status');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('stripe_subscription_id');
            $table->index('status');
            $table->index('plan');
        });

        Schema::create('income_streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform');
            $table->string('platform_account_id')->nullable();
            $table->binary('api_key_encrypted')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_post_enabled')->default(false);
            $table->json('posting_schedule')->nullable();
            $table->json('subscription_tiers')->nullable();
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('monthly_revenue', 12, 2)->default(0);
            $table->integer('subscriber_count')->default(0);
            $table->integer('content_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_post_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('platform');
            $table->index('is_connected');
        });

        Schema::create('revenue_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('income_stream_id')->nullable()->constrained('income_streams')->nullOnDelete();
            $table->string('platform');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->timestamp('transaction_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('platform');
            $table->index('transaction_date');
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->string('platform');
            $table->string('platform_post_id')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('media_urls')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->timestamps();

            $table->index('platform');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('revenue_records');
        Schema::dropIfExists('income_streams');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('generations');
    }
};
