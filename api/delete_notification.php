<?php
// File: api/delete_notification.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Hanya metode POST yang diizinkan.']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Baca input JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Data input tidak valid.']);
    exit();
}

$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($notificationId <= 0 || $userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Hapus alert berdasarkan id dan user_id (pastikan milik user yang benar)
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Notifikasi berhasil dihapus.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Notifikasi tidak ditemukan atau sudah dihapus.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Delete Notification Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan database: ' . $e->getMessage()
    ]);
}
?>
