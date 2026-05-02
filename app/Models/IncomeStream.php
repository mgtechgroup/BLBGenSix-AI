<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeStream extends Model
{
    use HasFactory;

    const PLATFORM_ONLYFANS = 'onlyfans';
    const PLATFORM_FANSLY = 'fansly';
    const PLATFORM_MANYVIDS = 'manyvids';
    const PLATFORM_JFF = 'just_for_fans';
    const PLATFORM_CUSTOM = 'custom_store';

    protected $fillable = [
        'user_id',
        'platform',
        'platform_account_id',
        'api_key_encrypted',
        'is_connected',
        'is_active',
        'auto_post_enabled',
        'posting_schedule',
        'subscription_tiers',
        'total_revenue',
        'monthly_revenue',
        'subscriber_count',
        'content_count',
        'last_sync_at',
        'last_post_at',
        'metadata',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'is_active' => 'boolean',
        'auto_post_enabled' => 'boolean',
        'posting_schedule' => 'array',
        'subscription_tiers' => 'array',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
        'last_post_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRevenueThisMonth()
    {
        return $this->monthly_revenue ?? 0;
    }

    public function getRevenueTotal()
    {
        return $this->total_revenue ?? 0;
    }

    public function isConnected(): bool
    {
        return $this->is_connected && $this->is_active;
    }
}
