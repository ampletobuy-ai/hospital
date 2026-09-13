<?php

defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('hospital_env')) {
    /**
     * Read environment variable from .env file or process env.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function hospital_env($key, $default = null)
    {
        static $booted = false;

        if (!$booted) {
            hospital_env_boot();
            $booted = true;
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return hospital_env_cast($value);
        }

        return $default;
    }

    function hospital_env_boot($root = null)
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        if ($root === null) {
            $root = dirname(dirname(dirname(__FILE__)));
        }

        $envFile = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envFile)) {
            $loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        $loaded = true;
    }

    function hospital_env_bool($key, $default = false)
    {
        $value = hospital_env($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    function hospital_env_cast($value)
    {
        $lower = strtolower((string) $value);
        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }

        return $value;
    }
}
