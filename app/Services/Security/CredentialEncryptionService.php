<?php

namespace App\Services\Security;

use Config\Services;
use Throwable;

/**
 * Encrypts and decrypts sensitive fleet credentials (SSH passwords, REST API tokens) at rest.
 *
 * Encrypted values are stored with a versioned prefix: `enc:v1:<base64-payload>`
 * Legacy unencrypted values (not carrying the prefix) are returned as-is by decrypt(),
 * guaranteeing seamless backward compatibility with existing inventory records.
 */
class CredentialEncryptionService
{
    public const PREFIX = 'enc:v1:';

    protected $encrypter;

    public function __construct($encrypter = null)
    {
        $this->encrypter = $encrypter;
    }

    protected function getEncrypter()
    {
        if ($this->encrypter === null) {
            $this->encrypter = Services::encrypter();
        }
        return $this->encrypter;
    }

    /**
     * Encrypt a plaintext credential for storage at rest.
     * Returns unmodified if null, empty, or already encrypted.
     */
    public function encrypt(?string $plain): ?string
    {
        if ($plain === null) {
            return null;
        }

        $trimmed = trim($plain);
        if ($trimmed === '') {
            return $plain;
        }

        if ($this->isEncrypted($plain)) {
            return $plain;
        }

        try {
            $ciphertext = $this->getEncrypter()->encrypt($plain);
            return self::PREFIX . base64_encode($ciphertext);
        } catch (Throwable $e) {
            log_message('error', 'CredentialEncryptionService::encrypt failed: ' . $e->getMessage());
            return $plain;
        }
    }

    /**
     * Decrypt an encrypted credential from storage.
     * Returns unmodified if null, empty, or not starting with the encryption prefix.
     */
    public function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (!$this->isEncrypted($value)) {
            return $value;
        }

        try {
            $rawCipher = base64_decode(substr($value, strlen(self::PREFIX)), true);
            if ($rawCipher === false) {
                return $value;
            }

            return $this->getEncrypter()->decrypt($rawCipher);
        } catch (Throwable $e) {
            log_message('error', 'CredentialEncryptionService::decrypt failed: ' . $e->getMessage());
            return $value;
        }
    }

    /**
     * Check if a given string has the encryption prefix.
     */
    public function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return str_starts_with($value, self::PREFIX);
    }
}
