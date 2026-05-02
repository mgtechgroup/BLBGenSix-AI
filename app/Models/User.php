<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Asbiin\LaravelWebauthn\Traits\WebauthnAuthenticatable;

class User extends Authenticable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, WebauthnAuthenticatable;

    protected $fillable = [
        'email',
        'username',
        'date_of_birth',
        'is_verified_adult',
        'verification_status',
        'verified_at',
        'subscription_status',
        'subscription_plan',
        'subscription_ends_at',
        'trial_ends_at',
        'api_usage_count',
        'api_usage_limit',
        'credits_remaining',
        'is_banned',
        'ban_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_usage_count',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_verified_adult' => 'boolean',
        'verified_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'is_banned' => 'boolean',
    ];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function webauthnKeys()
    {
        return $this->hasMany(WebauthnKey::class);
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function incomeStreams()
    {
        return $this->hasMany(IncomeStream::class);
    }

    public function cryptoWallets()
    {
        return $this->hasMany(CryptoWallet::class);
    }

    public function cryptoPayments()
    {
        return $this->hasMany(CryptoPayment::class);
    }

    public function adRevenueRecords()
    {
        return $this->hasMany(AdRevenue::class);
    }

    public function contentPosts()
    {
        return $this->hasMany(ContentPost::class);
    }

    public function revenueRecords()
    {
        return $this->hasMany(RevenueRecord::class);
    }

    public function isActiveSubscriber(): bool
    {
        return $this->subscription_status === 'active'
            && $this->subscription_ends_at > now();
    }

    public function canGenerate(): bool
    {
        return $this->api_usage_count < $this->api_usage_limit
            && !$this->is_banned
            && $this->is_verified_adult;
    }

    public function hasReachedLimit(): bool
    {
        return $this->api_usage_count >= $this->api_usage_limit;
    }

    public function incrementUsage()
    {
        $this->increment('api_usage_count');
    }
}
