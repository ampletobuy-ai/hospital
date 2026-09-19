<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laravel-compatible AES-256-CBC Encrypter (payload + HMAC) for pending signup passwords.
 */
class Portal_signup_crypt
{
    public static function encrypt($plain)
    {
        $plain = (string) $plain;
        $key = self::keyBytes();
        if ($key === null) {
            throw new RuntimeException('PORTAL_SIGNUP_CRYPT_KEY is required for pending signup encryption.');
        }

        $iv = random_bytes(16);
        $value = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        if ($value === false) {
            throw new RuntimeException('Failed to encrypt signup password.');
        }

        $ivB64 = base64_encode($iv);
        $mac = hash_hmac('sha256', $ivB64 . $value, $key);
        $json = json_encode(array(
            'iv' => $ivB64,
            'value' => $value,
            'mac' => $mac,
            'tag' => '',
        ), JSON_UNESCAPED_SLASHES);

        return base64_encode($json);
    }

    /**
     * Decrypt Laravel Encrypter payload (AES-256-CBC) from retail-pos / bookingengine portal.
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

        $ivB64 = (string) $payload['iv'];
        $value = (string) $payload['value'];
        if (!empty($payload['mac'])) {
            $expected = hash_hmac('sha256', $ivB64 . $value, $key);
            if (!hash_equals($expected, (string) $payload['mac'])) {
                return null;
            }
        }

        $iv = base64_decode($ivB64, true);
        if ($iv === false) {
            return null;
        }

        $plain = openssl_decrypt($value, 'AES-256-CBC', $key, 0, $iv);
        if ($plain === false) {
            // Legacy hospital decrypt (raw ciphertext)
            $raw = base64_decode($value, true);
            if ($raw !== false) {
                $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            }
        }

        return $plain === false ? null : $plain;
    }

    /**
     * Pack password + optional signup profile for pending paid checkouts (no schema change).
     */
    public static function packSignupSecret($password, array $signupProfile = array())
    {
        $payload = array(
            '_hospital_signup' => 1,
            'password' => (string) $password,
            'signup_profile' => $signupProfile,
        );

        return self::encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{password:?string,signup_profile:array}
     */
    public static function unpackSignupSecret($encrypted)
    {
        $plain = self::decrypt($encrypted);
        if ($plain === null || $plain === '') {
            return array('password' => null, 'signup_profile' => array());
        }

        $decoded = json_decode($plain, true);
        if (is_array($decoded) && !empty($decoded['_hospital_signup']) && isset($decoded['password'])) {
            $profile = isset($decoded['signup_profile']) && is_array($decoded['signup_profile'])
                ? $decoded['signup_profile']
                : array();

            return array(
                'password' => (string) $decoded['password'],
                'signup_profile' => $profile,
            );
        }

        return array('password' => $plain, 'signup_profile' => array());
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
