<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function log(string $action, string $category = 'general', array $context = []): void
    {
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'category' => $category,
            'ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'device_fingerprint' => request()->header('X-Device-Fingerprint'),
            'context' => $this->sanitizeContext($context),
            'request_id' => request()->header('X-Request-ID', uniqid('req_')),
        ];

        // Write to secure log
        $logPath = storage_path('logs/security/audit-' . now()->format('Y-m') . '.log');
        $directory = dirname($logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        // Append-only: open in append mode
        file_put_contents($logPath, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

        // Also send to monitoring if configured
        if (config('app.security.intrusion_detection.enabled', false)) {
            $this->checkForIntrusion($entry);
        }

        Log::channel('audit')->info($action, $entry);
    }

    public function logLogin($userId, $deviceId, $method = 'biometric'): void
    {
        $this->log('user.login', 'authentication', [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'method' => $method,
            'ip' => request()->ip(),
        ]);
    }

    public function logLogout($userId): void
    {
        $this->log('user.logout', 'authentication', [
            'user_id' => $userId,
        ]);
    }

    public function logFailedLogin(string $email, string $reason = 'unknown'): void
    {
        $this->log('login.failed', 'security', [
            'email' => $email,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);

        // Track failed attempts for brute force detection
        $cacheKey = 'failed_logins:' . request()->ip();
        $attempts = cache()->increment($cacheKey);
        cache()->put($cacheKey, $attempts, now()->addMinutes(15));

        if ($attempts >= 10) {
            $this->log('brute_force.detected', 'security', [
                'ip' => request()->ip(),
                'attempts' => $attempts,
            ]);
            
            // Auto-block IP
            cache()->put("blocked_ip:" . request()->ip(), true, now()->addHours(24));
        }
    }

    public function logGeneration(string $type, string $generationId, array $params = []): void
    {
        $this->log('generation.created', 'content', [
            'type' => $type,
            'generation_id' => $generationId,
            'parameters' => $this->sanitizeParams($params),
        ]);
    }

    public function logPayment(string $type, float $amount, string $currency, string $transactionId): void
    {
        $this->log('payment.processed', 'billing', [
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $transactionId,
        ]);
    }

    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->log($event, 'security', $context);
    }

    public function logDataAccess(string $dataType, string $recordId, string $action = 'read'): void
    {
        $this->log("data.{$action}", 'data_access', [
            'data_type' => $dataType,
            'record_id' => $recordId,
        ]);
    }

    public function logPermissionChange(string $userId, string $permission, string $action): void
    {
        $this->log('permission.changed', 'access_control', [
            'user_id' => $userId,
            'permission' => $permission,
            'action' => $action,
        ]);
    }

    protected function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'api_key', 'credit_card'];
        
        foreach ($context as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $context[$key] = '[REDACTED]';
                }
            }
        }

        return $context;
    }

    protected function sanitizeParams(array $params): array
    {
        unset($params['prompt'], $params['negative_prompt']);
        return array_keys($params);
    }

    protected function checkForIntrusion(array $entry): void
    {
        // Rate limit detection
        $ip = $entry['ip'];
        $requestsPerMinute = cache()->get("rate:{$ip}", 0);
        
        if ($requestsPerMinute > 1000) {
            $this->log('intrusion.ratelimit_exceeded', 'security', [
                'ip' => $ip,
                'requests' => $requestsPerMinute,
            ]);
        }

        // SQL injection pattern detection
        $userAgent = $entry['context']['user_agent'] ?? '';
        $sqlPatterns = ['union select', 'or 1=1', 'drop table', '--', '/*'];
        
        foreach ($sqlPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $this->log('intrusion.sql_injection_attempt', 'security', [
                    'ip' => $ip,
                    'pattern' => $pattern,
                ]);
                break;
            }
        }
    }
}
