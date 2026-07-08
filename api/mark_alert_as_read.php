<?php
// File: api/mark_alert_as_read.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => ''];

// Dapatkan data mentah dari body permintaan POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$userId = $data['user_id'] ?? null;
$alertId = $data['alert_id'] ?? null; // Bisa null jika ingin menandai semua
$markAll = $data['mark_all'] ?? false; // Boolean untuk menandai semua

if (!$userId) {
    $response['message'] = 'User ID is required.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getPDOConnection();

    if (!$pdo) {
        $response['message'] = 'Database connection failed.';
        echo json_encode($response);
        exit();
    }

    if ($markAll) {
        // Tandai semua notifikasi milik user sebagai sudah dibaca
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $response['status'] = 'success';
        $response['message'] = 'Semua notifikasi berhasil ditandai sebagai sudah dibaca.';
    } elseif ($alertId) {
        // Tandai notifikasi spesifik sebagai sudah dibaca
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$alertId, $userId]);
        if ($stmt->rowCount() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Notifikasi berhasil ditandai sebagai sudah dibaca.';
        } else {
            $response['message'] = 'Notifikasi tidak ditemukan atau sudah dibaca.';
        }
    } else {
        $response['message'] = 'Permintaan tidak valid. alert_id atau mark_all harus disediakan.';
    }

} catch (PDOException $e) {
    error_log("API mark_alert_as_read Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API mark_alert_as_read General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
