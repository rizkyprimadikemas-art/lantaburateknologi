<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth_helper.php';

if (isLoggedIn()) {
    header('Location: dashboard/');
    exit();
} else {
    header('Location: auth/login_page.php');
    exit();
}
?>
