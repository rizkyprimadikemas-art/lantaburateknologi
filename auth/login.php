<?php
// File: auth/login.php

// PENTING: Hanya panggil session_start() jika belum ada session yang aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gunakan __DIR__ untuk path yang absolut dan robust
require_once __DIR__ . '/../config/database.php';

// Enable error reporting untuk debugging (bisa dihapus di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("Login attempt - Username: $username");
    
    if (empty($username) || empty($password)) {
        $response['message'] = 'Username dan password harus diisi';
        echo json_encode($response);
        exit();
    }
    
    try {
        $pdo = getPDOConnection();
        error_log("Database connected successfully");
        
        // Cari user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("User not found: $username");
            $response['message'] = 'Username/email tidak ditemukan';
            echo json_encode($response);
            exit();
        }
        
        error_log("User found: " . print_r($user, true));
        error_log("Input password: $password");
        error_log("Stored hash: " . $user['password_hash']);
        
        // Verifikasi password
        if (!password_verify($password, $user['password_hash'])) {
            error_log("Password verification failed");
            $response['message'] = 'Password salah';
            echo json_encode($response);
            exit();
        }
        
        error_log("Password verified successfully");
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Simpan session ke database
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
            $expiresAt
        ]);
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Set session cookie (7 hari)
        setcookie('session_token', $sessionToken, time() + (7 * 24 * 3600), '/', '', false, true);
        
        // Set session PHP juga
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        
        $response['status'] = 'success';
        $response['message'] = 'Login berhasil';
        $response['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ];
        
        error_log("Login successful for user: " . $user['username']);
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan. Silakan coba lagi.';
    }
}

echo json_encode($response);
?>
