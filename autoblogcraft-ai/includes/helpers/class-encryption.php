<?php
/**
 * Encryption Helpers
 *
 * PBKDF2-based encryption for API keys
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Encryption
 *
 * Secure encryption/decryption using PBKDF2 key derivation
 */
class Encryption {

    /**
     * Encryption algorithm
     */
    const ALGORITHM = 'AES-256-CBC';

    /**
     * PBKDF2 iterations (100,000 minimum for 2026)
     */
    const PBKDF2_ITERATIONS = 100000;

    /**
     * Encrypt plaintext
     *
     * @param string $plaintext Text to encrypt
     * @return string Base64-encoded encrypted text
     */
    public static function encrypt($plaintext) {
        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // Return IV + ciphertext as base64
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt encrypted text
     *
     * @param string $encrypted Base64-encoded encrypted text
     * @return string|false Decrypted plaintext or false on failure
     */
    public static function decrypt($encrypted) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted);

        if ($data === false) {
            return false;
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);

        return openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Get encryption key using PBKDF2
     *
     * Uses WordPress AUTH_KEY and SECURE_AUTH_KEY as salt
     *
     * @return string Binary encryption key (32 bytes)
     */
    private static function get_encryption_key() {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            throw new \Exception('WordPress security keys not defined in wp-config.php');
        }

        $salt = AUTH_KEY . SECURE_AUTH_KEY;

        // PBKDF2 with 100,000 iterations for key stretching
        return hash_pbkdf2(
            'sha256',              // Hash algorithm
            $salt,                 // Password/salt
            'AutoBlogCraft',       // Additional salt
            self::PBKDF2_ITERATIONS, // Iterations
            32,                    // Key length (256 bits)
            true                   // Return raw binary
        );
    }

    /**
     * Generate SHA256 hash for duplicate detection
     *
     * @param string $text Text to hash
     * @return string SHA256 hash (64 characters)
     */
    public static function hash($text) {
        return hash('sha256', $text);
    }

    /**
     * Verify that OpenSSL extension is available
     *
     * @return bool
     */
    public static function is_available() {
        return extension_loaded('openssl');
    }
}
