<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdCampaign extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending_approval';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'space_id', 'start_date', 'end_date',
        'budget_total', 'budget_remaining', 'creative_url',
        'target_audience', 'payment_method', 'status',
        'impressions', 'clicks',
    ];

    protected $casts = [
        'budget_total' => 'decimal:10,2',
        'budget_remaining' => 'decimal:10,2',
        'target_audience' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->budget_remaining > 0
            && $this->end_date >= now();
    }
}
