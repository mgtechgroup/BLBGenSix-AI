<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    const PLAN_STARTER = 'starter';
    const PLAN_PRO = 'pro';
    const PLAN_ENTERPRISE = 'enterprise';

    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_TRIAL = 'trial';

    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'plan',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'ends_at',
        'amount',
        'currency',
        'payment_method',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (!$this->ends_at || $this->ends_at > now());
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at > now();
    }

    public function getUsageLimits(): array
    {
        return match ($this->plan) {
            self::PLAN_STARTER => [
                'images_per_day' => 50,
                'videos_per_day' => 5,
                'text_tokens_per_day' => 50000,
                'body_models_per_day' => 3,
                'max_resolution' => '1024x1024',
                'max_video_duration' => 30,
                'priority_queue' => false,
            ],
            self::PLAN_PRO => [
                'images_per_day' => 500,
                'videos_per_day' => 50,
                'text_tokens_per_day' => 500000,
                'body_models_per_day' => 20,
                'max_resolution' => '2048x2048',
                'max_video_duration' => 120,
                'priority_queue' => true,
            ],
            self::PLAN_ENTERPRISE => [
                'images_per_day' => -1,
                'videos_per_day' => -1,
                'text_tokens_per_day' => -1,
                'body_models_per_day' => -1,
                'max_resolution' => '4K',
                'max_video_duration' => 300,
                'priority_queue' => true,
            ],
        };
    }
}
