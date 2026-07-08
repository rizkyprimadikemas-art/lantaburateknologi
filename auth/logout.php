<?php
// File: auth/logout.php

// PENTING: Hanya panggil session_start() jika belum ada session yang aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gunakan __DIR__ untuk path yang absolut dan robust
require_once __DIR__ . '/../config/database.php';

if (isset($_COOKIE['session_token'])) {
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->execute([$_COOKIE['session_token']]);
    } catch (PDOException $e) {
        // Abaikan error di sini, yang penting session dihapus dari client
        error_log("Error updating session status on logout: " . $e->getMessage());
    }
    
    // Hapus cookie
    setcookie('session_token', '', time() - 3600, '/', '', false, true);
}

// Hapus session PHP
session_destroy();
$_SESSION = array(); // Clear session variables

// Redirect ke login
header('Location: ../auth/login_page.php');
exit();
?>
