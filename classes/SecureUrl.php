<?php

require_once __DIR__ . '/EncryptionManager.php';
require_once __DIR__ . '/Security.php';

/**
 * Secure token helper for building and validating signed URLs.
 */
class SecureUrl
{
    private const CONTEXT = 'signed_url';

    /**
     * Generate a signed token for the given path and parameters.
     */
    public static function generateToken(string $path, array $params = [], int $ttlSeconds = 900): string
    {
        $payload = [
            'path' => self::normalizePath($path),
            'params' => $params,
            'exp' => $ttlSeconds > 0 ? (time() + $ttlSeconds) : null,
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return EncryptionManager::encrypt($json, self::CONTEXT);
    }

    /**
     * Attach an existing token to the provided path.
     */
    public static function attachToken(string $path, string $token): string
    {
        $separator = strpos($path, '?') === false ? '?' : '&';
        return $path . $separator . 'token=' . rawurlencode($token);
    }

    /**
     * Generate a signed path (without domain) combining path and token.
     */
    public static function buildSignedPath(string $path, array $params = [], int $ttlSeconds = 900): string
    {
        $token = self::generateToken($path, $params, $ttlSeconds);
        return self::attachToken(self::normalizePath($path), $token);
    }

    /**
     * Validate a token for the expected path and return decoded parameters.
     */
    public static function validateToken(string $path, string $token): ?array
    {
        $decoded = EncryptionManager::decrypt($token, self::CONTEXT);
        if ($decoded === '') {
            Security::logSecurityEvent('SIGNED_URL_DECRYPT_FAILED', ['path' => $path], 'MEDIUM');
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            Security::logSecurityEvent('SIGNED_URL_INVALID_PAYLOAD', ['path' => $path], 'MEDIUM');
            return null;
        }

        if (!isset($payload['path']) || self::normalizePath($payload['path']) !== self::normalizePath($path)) {
            Security::logSecurityEvent('SIGNED_URL_PATH_MISMATCH', ['path' => $path], 'HIGH');
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] !== null && time() > (int) $payload['exp']) {
            Security::logSecurityEvent('SIGNED_URL_EXPIRED', ['path' => $path], 'INFO');
            return null;
        }

        return $payload['params'] ?? [];
    }

    private static function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}
