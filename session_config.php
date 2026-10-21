<?php
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}
ini_set('session.save_path', $session_path);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>