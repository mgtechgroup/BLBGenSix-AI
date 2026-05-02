<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    const METHOD_BIOMETRIC = 'biometric';
    const METHOD_ID_UPLOAD = 'id_upload';
    const METHOD_LIVENESS = 'liveness_check';

    protected $fillable = [
        'user_id',
        'method',
        'status',
        'document_type',
        'document_url',
        'document_verified_url',
        'biometric_data',
        'liveness_score',
        'age_verified',
        'verification_provider',
        'provider_response',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'biometric_data' => 'encrypted:array',
        'provider_response' => 'array',
        'age_verified' => 'boolean',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && (!$this->expires_at || $this->expires_at > now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at <= now();
    }
}
