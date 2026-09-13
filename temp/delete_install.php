<?php
// Standalone script — does NOT bootstrap CodeIgniter.
// Deletes itself, then the install directory, then redirects to the login page.

function _rm_recursive($dir) {
    if (!is_dir($dir)) return;
    $items = glob($dir . '/*');
    if ($items) {
        foreach ($items as $item) {
            if (is_dir($item)) {
                _rm_recursive($item);
            } else {
                @unlink($item);
            }
        }
    }
    @rmdir($dir);
}

// Best-effort self-delete. Reliable on Linux (most production hosts); on
// Windows the OS may keep this file locked while it's executing, so this
// can silently no-op there — harmless either way, since re-running this
// script after the install folder is gone does nothing.
@unlink(__FILE__);
@rmdir(__DIR__);

_rm_recursive(dirname(__DIR__) . '/application/controllers/install');

$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base      = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/') . '/';
$login_url = $proto . '://' . $_SERVER['HTTP_HOST'] . $base . 'site/login';

header('Location: ' . $login_url);
exit();
