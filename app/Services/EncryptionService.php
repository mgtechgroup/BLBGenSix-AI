<?php

namespace App\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EncryptionService
{
    protected ?Key $key = null;
    protected array $keyRotationHistory = [];

    public function __construct()
    {
        $this->loadKey();
    }

    protected function loadKey(): void
    {
        $keyPath = storage_path('app/encryption/master.key');
        
        if (!file_exists($keyPath)) {
            $this->generateMasterKey($keyPath);
        }

        $keyContent = file_get_contents($keyPath);
        $this->key = Key::loadFromAsciiSafeString($keyContent);

        // Load rotation history
        $rotationPath = storage_path('app/encryption/key-rotation.json');
        if (file_exists($rotationPath)) {
            $this->keyRotationHistory = json_decode(file_get_contents($rotationPath), true);
        }
    }

    protected function generateMasterKey(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $key = Key::createNewRandomKey();
        file_put_contents($path, $key->saveToAsciiSafeString(), LOCK_EX);
        chmod($path, 0400);

        Log::info('Master encryption key generated');
    }

    public function encrypt(string $plaintext): string
    {
        try {
            return Crypto::encrypt($plaintext, $this->key);
        } catch (\Exception $e) {
            Log::error('Encryption failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Encryption failed');
        }
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            return Crypto::decrypt($ciphertext, $this->key);
        } catch (\Defuse\Crypto\Exception\CryptoException $e) {
            // Try rotation history
            foreach ($this->keyRotationHistory as $oldKey) {
                try {
                    return Crypto::decrypt($ciphertext, Key::loadFromAsciiSafeString($oldKey));
                } catch (\Exception $inner) {
                    continue;
                }
            }
            
            Log::error('Decryption failed - all keys exhausted', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Decryption failed');
        }
    }

    public function encryptArray(array $data): array
    {
        $encrypted = [];
        foreach ($data as $key => $value) {
            $encrypted[$key] = is_string($value) ? $this->encrypt($value) : $this->encrypt(json_encode($value));
        }
        return $encrypted;
    }

    public function decryptArray(array $encrypted): array
    {
        $decrypted = [];
        foreach ($encrypted as $key => $value) {
            $decrypted[$key] = $this->decrypt($value);
            // Try to decode JSON
            $json = json_decode($decrypted[$key], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decrypted[$key] = $json;
            }
        }
        return $decrypted;
    }

    public function rotateKey(): void
    {
        // Save current key to rotation history
        $this->keyRotationHistory[] = $this->key->saveToAsciiSafeString();

        // Generate new key
        $keyPath = storage_path('app/encryption/master.key');
        $this->generateMasterKey($keyPath);
        $this->loadKey();

        // Save rotation history
        $rotationPath = storage_path('app/encryption/key-rotation.json');
        file_put_contents($rotationPath, json_encode($this->keyRotationHistory), LOCK_EX);

        Log::info('Encryption key rotated');
    }

    public function hashSensitive(string $data, string $salt = ''): string
    {
        $pepper = config('app.hash_pepper', env('HASH_PEPPER'));
        return hash('sha3-512', $data . $salt . $pepper);
    }

    public function verifyHash(string $data, string $hash, string $salt = ''): bool
    {
        return hash_equals($this->hashSensitive($data, $salt), $hash);
    }

    public function generateSecureToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public function constantTimeCompare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
