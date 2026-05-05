<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZeroTrustTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected string $fingerprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_verified_adult' => true,
            'is_banned' => false,
            'subscription_plan' => 'pro',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->fingerprint = hash('sha256', 'Test-Agent|canvas|webgl|1920x1080|UTC|en-US|Win32|4|8GB');
    }

    protected function getDeviceHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Test-Agent',
            'X-Device-Canvas' => 'canvas',
            'X-Device-WebGL' => 'webgl',
            'X-Screen-Resolution' => '1920x1080',
            'X-Timezone' => 'UTC',
            'X-Language' => 'en-US',
            'X-Platform' => 'Win32',
            'X-CPU-Cores' => '4',
            'X-Memory' => '8GB',
        ];
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/music/stats');
        $response->assertStatus(401);
    }

    public function test_banned_user_blocked_by_zero_trust(): void
    {
        $this->user->update(['is_banned' => true, 'ban_reason' => 'Test ban']);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Account banned']);
    }

    public function test_unverified_adult_blocked_by_zero_trust(): void
    {
        $this->user->update(['is_verified_adult' => false]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Adult verification required']);
    }

    public function test_untrusted_device_blocked(): void
    {
        $response = $this->withHeaders($this->getDeviceHeaders())
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Untrusted device']);
    }

    public function test_registered_trusted_device_allowed(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
        ]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_expired_session_requires_reverification(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now()->subSeconds(1000),
            'last_ip' => '127.0.0.1',
        ]);

        config(['app.zero_trust.session_rotation' => 900]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Session expired - biometric re-verification required'])
            ->assertJson(['requires_biometric' => true]);
    }

    public function test_ip_change_requires_reverification(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now(),
            'last_ip' => '10.0.0.1',
        ]);

        config(['app.zero_trust.ip_validation' => true]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(401)
            ->assertJson(['error' => 'IP address changed - re-verification required']);
    }

    public function test_device_fingerprint_generated_correctly(): void
    {
        $response = $this->withHeaders($this->getDeviceHeaders())
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403);

        $device = Device::where('fingerprint', $this->fingerprint)->first();
        $this->assertNull($device);
    }

    public function test_multiple_requests_update_last_seen(): void
    {
        $device = Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now()->subMinutes(5),
            'last_ip' => '127.0.0.1',
        ]);

        $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/music/stats');

        $device->refresh();
        $this->assertGreaterThan(now()->subMinute(), $device->last_seen_at);
    }

    public function test_cold_wallet_enforcement(): void
    {
        config(['app.zero_trust.require_cold_wallet' => true]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403);
    }

    public function test_admin_endpoint_requires_admin_role(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
        ]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/admin/feature-flags');

        $response->assertStatus(403);
    }

    public function test_zero_trust_allows_sequential_requests(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => true,
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
        ]);

        $headers = $this->getDeviceHeaders();
        $serverVars = ['REMOTE_ADDR' => '127.0.0.1'];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders($headers)
                ->withServerVariables($serverVars)
                ->getJson('/api/v1/music/stats');
            $response->assertStatus(200);
        }
    }

    public function test_device_registration_endpoint_available(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/devices/register', [
                'device_name' => 'Test Device',
                'fingerprint' => $this->fingerprint,
            ]);

        $response->assertStatus(200);
    }

    public function test_inactive_device_blocked(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'fingerprint' => $this->fingerprint,
            'is_trusted' => false,
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
        ]);

        $response = $this->withHeaders($this->getDeviceHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/v1/music/stats');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Untrusted device']);
    }
}
