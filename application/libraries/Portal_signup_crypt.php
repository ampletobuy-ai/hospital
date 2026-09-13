<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Portal_signup_crypt
{
    /**
     * Decrypt Laravel Encrypter payload (AES-256-CBC) from retail-pos portal.
     */
    public static function decrypt($encrypted)
    {
        $encrypted = trim((string) $encrypted);
        if ($encrypted === '') {
            return null;
        }

        $key = self::keyBytes();
        if ($key === null) {
            return null;
        }

        $payload = json_decode(base64_decode($encrypted, true), true);
        if (!is_array($payload) || empty($payload['iv']) || empty($payload['value'])) {
            return null;
        }

        $iv = base64_decode((string) $payload['iv'], true);
        $value = base64_decode((string) $payload['value'], true);
        if ($iv === false || $value === false) {
            return null;
        }

        $plain = openssl_decrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }

    private static function keyBytes()
    {
        $CI =& get_instance();
        $CI->load->config('tenancy-config');
        $candidates = array(
            (string) $CI->config->item('portal_signup_crypt_key'),
            (string) hospital_env('PORTAL_SIGNUP_CRYPT_KEY', ''),
        );

        foreach ($candidates as $raw) {
            $decoded = self::decodeKey($raw);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    private static function decodeKey($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || stripos($raw, 'CHANGE_ME') !== false) {
            return null;
        }

        if (strpos($raw, 'base64:') === 0) {
            $decoded = base64_decode(substr($raw, 7), true);

            return ($decoded === false || $decoded === '') ? null : $decoded;
        }

        return $raw;
    }
}
