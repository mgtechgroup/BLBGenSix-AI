<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'fingerprint',
        'device_name',
        'platform',
        'browser',
        'os',
        'os_version',
        'last_ip',
        'last_user_agent',
        'is_trusted',
        'is_primary',
        'biometric_enabled',
        'biometric_type',
        'registered_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'is_primary' => 'boolean',
        'biometric_enabled' => 'boolean',
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $user;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function generateFingerprint(array $request): string
    {
        $components = [
            $request['user_agent'] ?? '',
            $request['screen_resolution'] ?? '',
            $request['timezone'] ?? '',
            $request['language'] ?? '',
            $request['platform'] ?? '',
            $request['cpu_cores'] ?? '',
            $request['memory'] ?? '',
            $request['canvas_hash'] ?? '',
            $request['webgl_hash'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }
}
