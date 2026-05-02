<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoWallet extends Model
{
    use HasFactory;

    const TYPE_LEDGER = 'ledger';
    const TYPE_TREZOR = 'trezor';
    const TYPE_BITBOX = 'bitbox02';
    const TYPE_SAFE3 = 'safe3';
    const TYPE_KEYSTONE = 'keystone';
    const TYPE_NGRAVE = 'ngrave_zero';
    const TYPE_DEVICE_BOUND = 'device_bound_passkey';
    const TYPE_HARDWARE_EMBEDDED = 'hardware_embedded';

    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_VERIFIED = 'verified';
    const STATUS_ACTIVE = 'active';
    const STATUS_FROZEN = 'frozen';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'device_id',
        'wallet_type',
        'wallet_model',
        'network',
        'address',
        'public_key',
        'address_signature_proof',
        'is_cold_wallet',
        'is_device_bound',
        'binding_device_fingerprint',
        'binding_passkey_credential_id',
        'verification_method',
        'verification_score',
        'status',
        'last_used_at',
        'total_deposits',
        'total_withdrawals',
        'metadata',
    ];

    protected $hidden = [
        'public_key',
        'address_signature_proof',
        'metadata',
    ];

    protected $casts = [
        'is_cold_wallet' => 'boolean',
        'is_device_bound' => 'boolean',
        'verification_score' => 'float',
        'last_used_at' => 'datetime',
        'total_deposits' => 'decimal:18,8',
        'total_withdrawals' => 'decimal:18,8',
        'metadata' => 'encrypted:array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->is_cold_wallet
            && $this->verification_score >= 0.9;
    }

    public function isHotWallet(): bool
    {
        return !$this->is_cold_wallet;
    }

    public function canWithdraw(): bool
    {
        return $this->isVerified()
            && $this->status === self::STATUS_ACTIVE
            && $this->is_device_bound;
    }

    public function canDeposit(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function requiresReverification(): bool
    {
        return $this->last_used_at === null
            || $this->last_used_at->diffInHours(now()) > 24;
    }

    public function scopeColdOnly($query)
    {
        return $query->where('is_cold_wallet', true)
            ->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDeviceBound($query)
    {
        return $query->where('is_device_bound', true);
    }

    public function scopeByNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('is_cold_wallet', true)
            ->where('verification_score', '>=', 0.9);
    }

    public function freeze(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FROZEN,
            'metadata' => array_merge($this->metadata ?? [], [
                'frozen_reason' => $reason,
                'frozen_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function revoke(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'metadata' => array_merge($this->metadata ?? [], [
                'revoked_reason' => $reason,
                'revoked_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
