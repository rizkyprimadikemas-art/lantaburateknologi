<?php
// File: includes/auth_helper.php

// PENTING: Hanya panggil session_start() jika belum ada session yang aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gunakan __DIR__ untuk path yang absolut dan robust
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Cek cookie session_token jika session PHP belum ada
    if (isset($_COOKIE['session_token'])) {
        try {
            $pdo = getPDOConnection();
            $stmt = $pdo->prepare("
                SELECT u.* FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.session_token = ? 
                AND us.expires_at > NOW() 
                AND us.is_active = 1
                AND u.is_active = 1
            ");
            $stmt->execute([$_COOKIE['session_token']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Set session PHP
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("Auth helper error: " . $e->getMessage());
        }
    }
    
    return false;
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name']
        ];
    }
    return null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth/login_page.php');
        exit();
    }
}

/**
 * Menghasilkan dan menyimpan kode registrasi perangkat baru.
 * @param int $userId ID pengguna yang mendaftarkan kode.
 * @param string $deviceName Nama perangkat yang akan didaftarkan.
 * @return array|false Array berisi 'code' dan 'expires_at' jika berhasil, false jika gagal.
 */
function generateDeviceRegistrationCode($userId, $deviceName) {
    try {
        $pdo = getPDOConnection();
        
        // Generate kode unik (contoh: LT-ABCDEF)
        // Pastikan kode benar-benar unik
        $code = '';
        $isUnique = false;
        while (!$isUnique) {
            $code = 'LT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $stmt = $pdo->prepare("SELECT id FROM device_registration_codes WHERE registration_code = ?");
            $stmt->execute([$code]);
            if (!$stmt->fetch()) {
                $isUnique = true;
            }
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // Kode berlaku 24 jam
        
        // Simpan kode registrasi ke tabel device_registration_codes
        $stmt = $pdo->prepare("
            INSERT INTO device_registration_codes (user_id, registration_code, device_name_temp, expires_at, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $code, $deviceName, $expiresAt]);
        
        return ['code' => $code, 'expires_at' => $expiresAt];
    } catch (PDOException $e) {
        error_log("Generate device registration code error: " . $e->getMessage());
        return false;
    }
}

// Fungsi placeholder untuk generateRegistrationCode sebelumnya, bisa dihapus atau diubah
function generateRegistrationCode($userId) {
    return generateDeviceRegistrationCode($userId, 'Default Device Name');
}

?>
