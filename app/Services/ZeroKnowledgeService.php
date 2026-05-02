<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZeroKnowledgeService
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function createVault(User $user, string $masterPassword): array
    {
        // Generate user's personal key pair
        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

        // Derive encryption key from master password using Argon2id
        $derivedKey = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $this->getSalt($user->id),
            $masterPassword,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        // Encrypt secret key with derived key
        $encryptedSecretKey = sodium_crypto_secretbox($secretKey, random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $derivedKey);

        // Store public key, keep secret key encrypted
        $user->update([
            'zk_public_key' => base64_encode($publicKey),
            'zk_encrypted_secret_key' => base64_encode($encryptedSecretKey),
            'zk_salt' => base64_encode($this->getSalt($user->id)),
        ]);

        return [
            'public_key' => base64_encode($publicKey),
            'message' => 'Zero-knowledge vault created. NEVER lose your master password.',
        ];
    }

    public function encryptContentForUser(User $user, string $content, string $masterPassword): string
    {
        // Derive key from master password
        $derivedKey = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            base64_decode($user->zk_salt),
            $masterPassword,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        // Encrypt content with user's derived key
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($content, $nonce, $derivedKey);

        return base64_encode($nonce . $encrypted);
    }

    public function decryptContentForUser(User $user, string $encryptedContent, string $masterPassword): string
    {
        $derivedKey = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            base64_decode($user->zk_salt),
            $masterPassword,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $decoded = base64_decode($encryptedContent);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $derivedKey);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed - incorrect master password');
        }

        return $decrypted;
    }

    public function signData(User $user, string $data, string $masterPassword): string
    {
        $secretKey = $this->getSecretKey($user, $masterPassword);
        $signature = sodium_crypto_sign_detached($data, $secretKey);
        return base64_encode($signature);
    }

    public function verifySignature(User $user, string $data, string $signature): bool
    {
        $publicKey = base64_decode($user->zk_public_key);
        return sodium_crypto_sign_verify_detached(base64_decode($signature), $data, $publicKey);
    }

    public function sealData(string $data, string $recipientPublicKey): string
    {
        // Anonymous sealed box - only recipient can open
        return sodium_crypto_box_seal($data, base64_decode($recipientPublicKey));
    }

    public function openSealedData(User $user, string $sealedData, string $masterPassword): string
    {
        $keyPair = $this->getKeyPair($user, $masterPassword);
        return sodium_crypto_box_seal_open(base64_decode($sealedData), $keyPair);
    }

    protected function getSecretKey(User $user, string $masterPassword): string
    {
        $derivedKey = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            base64_decode($user->zk_salt),
            $masterPassword,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $encryptedSecretKey = base64_decode($user->zk_encrypted_secret_key);
        $nonce = substr($encryptedSecretKey, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($encryptedSecretKey, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_crypto_secretbox_open($ciphertext, $nonce, $derivedKey);
    }

    protected function getKeyPair(User $user, string $masterPassword): string
    {
        $secretKey = $this->getSecretKey($user, $masterPassword);
        $publicKey = base64_decode($user->zk_public_key);
        return $secretKey . $publicKey;
    }

    protected function getSalt(string $userId): string
    {
        $cacheKey = "zk_salt_{$userId}";
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        Cache::put($cacheKey, $salt, now()->addHours(24));

        return $salt;
    }

    public function wipeUserData(User $user): void
    {
        $user->update([
            'zk_public_key' => null,
            'zk_encrypted_secret_key' => null,
            'zk_salt' => null,
        ]);

        Log::warning('User zero-knowledge vault wiped', ['user_id' => $user->id]);
    }
}
