<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoPayment extends Model
{
    use HasFactory;

    const STATUS_AWAITING = 'awaiting_payment';
    const STATUS_DETECTED = 'payment_detected';
    const STATUS_PENDING = 'pending_confirmations';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'wallet_id', 'payment_id', 'network', 'token',
        'amount_usd', 'amount_crypto', 'exchange_rate',
        'sender_address', 'recipient_address', 'order_type', 'order_id',
        'tx_hash', 'confirmations', 'status', 'expires_at', 'confirmed_at',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_crypto' => 'decimal:18,8',
        'exchange_rate' => 'decimal:18,8',
        'confirmations' => 'integer',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function wallet() { return $this->belongsTo(CryptoWallet::class); }

    public function isExpired(): bool { return $this->expires_at && $this->expires_at < now(); }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
}
