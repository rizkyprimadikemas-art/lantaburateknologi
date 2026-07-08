<?php
// File: auth/register.php

// PENTING: Hanya panggil session_start() jika belum ada session yang aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gunakan __DIR__ untuk path yang absolut dan robust
require_once __DIR__ . '/../config/database.php';

// Enable error reporting (bisa dihapus di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    error_log("Register attempt - Username: $username, Email: $email");
    
    // Validasi
    if (empty($username) || empty($email) || empty($password)) {
        $response['message'] = 'Username, email, dan password harus diisi';
        echo json_encode($response);
        exit();
    }
    
    if ($password !== $confirmPassword) {
        $response['message'] = 'Password dan konfirmasi password tidak sama';
        echo json_encode($response);
        exit();
    }
    
    if (strlen($password) < 6) {
        $response['message'] = 'Password minimal 6 karakter';
        echo json_encode($response);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Format email tidak valid';
        echo json_encode($response);
        exit();
    }
    
    try {
        $pdo = getPDOConnection();
        error_log("Database connected for registration");
        
        // Cek username/email sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $response['message'] = 'Username atau email sudah terdaftar';
            echo json_encode($response);
            exit();
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        error_log("Password hash created: " . $passwordHash);
        
        // Insert user baru
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, full_name, company, phone) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $company, $phone]);
        
        $userId = $pdo->lastInsertId();
        
        error_log("User registered successfully. ID: $userId");
        
        $response['status'] = 'success';
        $response['message'] = 'Registrasi berhasil! Silakan login.';
        $response['user_id'] = $userId;
        
    } catch (PDOException $e) {
        error_log("Database error during registration: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
