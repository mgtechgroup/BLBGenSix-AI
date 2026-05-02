<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'space_id', 'name', 'size', 'location',
        'impressions', 'clicks', 'ctr', 'revenue_earned',
        'is_active', 'is_approved',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:4,2',
        'revenue_earned' => 'decimal:10,2',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function calculateCTR(): float
    {
        if ($this->impressions === 0) return 0;
        return ($this->clicks / $this->impressions) * 100;
    }
}
