<?php

namespace App\Services\Security;

use App\Models\CoreConfigModel;
use Throwable;

/**
 * Service managing pfSense-compatible configuration backup encryption and decryption.
 *
 * Implements the exact cryptographic envelope used by pfSense's native Backup & Restore system:
 * - Cipher: AES-256-CBC with PKCS#7 padding
 * - Key Derivation: PBKDF2 with HMAC-SHA256 (500,000 iterations default)
 * - Salt: 8-byte cryptographic random salt prefixed with OpenSSL's 'Salted__' magic header
 * - Envelope: Base64 formatted in 64-character lines wrapped in '---- BEGIN config.xml ----'
 *   and '---- END config.xml ----' tags
 *
 * Configurations and backups encrypted with this service can be directly restored via
 * pfSense WebGUI under "Diagnostics > Backup & Restore" by checking "Configuration file is encrypted"
 * and entering the configured password.
 */
class PfSenseCryptoService
{
    public const DEFAULT_ITERATIONS = 500000;
    public const LEGACY_ITERATIONS = 10000;
    public const TAG = 'config.xml';
    public const HEADER = "---- BEGIN config.xml ----";
    public const FOOTER = "---- END config.xml ----";

    /**
     * Encrypt a plaintext pfSense XML configuration with a password.
     * Returns the formatted string ready for pfSense restore or download.
     *
     * @param string $xmlContent The raw XML string
     * @param string $password The encryption password
     * @param int $iterations PBKDF2 iteration count (default 500,000)
     * @return string The pfSense-compatible encrypted envelope
     */
    public static function encryptConfig(string $xmlContent, string $password, int $iterations = self::DEFAULT_ITERATIONS): string
    {
        $password = trim($password);
        if ($password === '' || self::isEncrypted($xmlContent)) {
            return $xmlContent;
        }

        try {
            // Generate 8-byte cryptographic salt
            $salt = random_bytes(8);

            // Derive 48 bytes (32 bytes AES-256 Key + 16 bytes IV) using PBKDF2-HMAC-SHA256
            $derived = openssl_pbkdf2($password, $salt, 48, $iterations, 'sha256');
            $key = substr($derived, 0, 32);
            $iv  = substr($derived, 32, 16);

            // Encrypt using AES-256-CBC
            $ciphertext = openssl_encrypt($xmlContent, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext === false) {
                log_message('error', 'PfSenseCryptoService: openssl_encrypt failed.');
                return $xmlContent;
            }

            // Pack OpenSSL envelope: "Salted__" (8 bytes) + salt (8 bytes) + ciphertext
            $rawPayload = 'Salted__' . $salt . $ciphertext;
            $b64 = base64_encode($rawPayload);

            // Format into 64-character lines matching pfSense tagfile_reformat()
            $lines = [];
            $len = strlen($b64);
            for ($offset = 0; $offset < $len; $offset += 64) {
                $lines[] = substr($b64, $offset, 64);
            }

            return self::HEADER . "\n" . implode("\n", $lines) . "\n" . self::FOOTER . "\n";
        } catch (Throwable $e) {
            log_message('error', 'PfSenseCryptoService::encryptConfig error: ' . $e->getMessage());
            return $xmlContent;
        }
    }

    /**
     * Decrypt a pfSense-compatible encrypted configuration envelope.
     *
     * @param string $content Encrypted envelope or raw string
     * @param string $password The decryption password
     * @return string|null Decrypted plaintext XML, or null if decryption fails
     */
    public static function decryptConfig(string $content, string $password): ?string
    {
        $password = trim($password);
        if ($password === '') {
            return null;
        }

        $b64Data = self::extractBase64Payload($content);
        if ($b64Data === null) {
            return null;
        }

        $rawPayload = base64_decode($b64Data, true);
        if ($rawPayload === false || strlen($rawPayload) < 16) {
            return null;
        }

        // Verify OpenSSL magic header "Salted__" (8 bytes)
        if (substr($rawPayload, 0, 8) !== 'Salted__') {
            return null;
        }

        $salt = substr($rawPayload, 8, 8);
        $ciphertext = substr($rawPayload, 16);

        // Attempt 1: Modern pfSense default (500,000 iterations PBKDF2 SHA-256)
        $decrypted = self::tryDecryptPbkdf2($ciphertext, $password, $salt, self::DEFAULT_ITERATIONS);
        if ($decrypted !== null && str_contains($decrypted, '<pfsense')) {
            return $decrypted;
        }

        // Attempt 2: Older pfSense default (10,000 iterations PBKDF2 SHA-256)
        $decrypted = self::tryDecryptPbkdf2($ciphertext, $password, $salt, self::LEGACY_ITERATIONS);
        if ($decrypted !== null && str_contains($decrypted, '<pfsense')) {
            return $decrypted;
        }

        // Attempt 3: Legacy OpenSSL EVP_BytesToKey (MD5 without PBKDF2)
        $decrypted = self::tryDecryptLegacyMd5($ciphertext, $password, $salt);
        if ($decrypted !== null && str_contains($decrypted, '<pfsense')) {
            return $decrypted;
        }

        return null;
    }

    /**
     * Check if a content string is in pfSense encrypted tag format.
     */
    public static function isEncrypted(string $content): bool
    {
        return stripos($content, self::HEADER) !== false;
    }

    /**
     * Extract the clean Base64 payload between pfSense delimiter tags.
     */
    public static function extractBase64Payload(string $content): ?string
    {
        $startPos = stripos($content, self::HEADER);
        $endPos   = stripos($content, self::FOOTER);

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            return null;
        }

        $bodyStart = $startPos + strlen(self::HEADER);
        $bodyLength = $endPos - $bodyStart;
        $body = substr($content, $bodyStart, $bodyLength);

        // Remove whitespace and newlines from Base64
        return preg_replace('/\s+/', '', $body);
    }

    /**
     * Retrieve global backup encryption settings from core_config table.
     *
     * @return array{enabled: bool, password: string, allow_plain: bool}
     */
    public static function getGlobalSettings(): array
    {
        try {
            $model = model(CoreConfigModel::class);
            $enableRow = $model->where('name', 'backup_encryption_enable')->first();
            $passRow   = $model->where('name', 'backup_encryption_password')->first();
            $plainRow  = $model->where('name', 'backup_encryption_allow_plain')->first();

            $enabled    = ($enableRow['value'] ?? '1') === '1';
            $password   = (string) ($passRow['value'] ?? '');
            $allowPlain = ($plainRow['value'] ?? '1') === '1';

            return [
                'enabled'     => $enabled && $password !== '',
                'password'    => $password,
                'allow_plain' => $allowPlain,
            ];
        } catch (Throwable $e) {
            log_message('error', 'PfSenseCryptoService::getGlobalSettings error: ' . $e->getMessage());
            return [
                'enabled'     => false,
                'password'    => '',
                'allow_plain' => true,
            ];
        }
    }

    /**
     * Internal helper to decrypt via PBKDF2.
     */
    private static function tryDecryptPbkdf2(string $ciphertext, string $password, string $salt, int $iterations): ?string
    {
        try {
            $derived = openssl_pbkdf2($password, $salt, 48, $iterations, 'sha256');
            $key = substr($derived, 0, 32);
            $iv  = substr($derived, 32, 16);
            $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            return $plain !== false ? $plain : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Internal helper for legacy OpenSSL MD5 key derivation.
     */
    private static function tryDecryptLegacyMd5(string $ciphertext, string $password, string $salt): ?string
    {
        try {
            $rounds = 3;
            $data = '';
            $d = '';
            for ($i = 0; $i < $rounds; $i++) {
                $d = md5($d . $password . $salt, true);
                $data .= $d;
            }
            $key = substr($data, 0, 32);
            $iv  = substr($data, 32, 16);
            $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            return $plain !== false ? $plain : null;
        } catch (Throwable) {
            return null;
        }
    }
}
