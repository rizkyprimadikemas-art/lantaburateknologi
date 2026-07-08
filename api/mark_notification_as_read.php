<?php
// File: api/mark_notification_as_read.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => 'Unauthorized'];

if (!isLoggedIn()) {
    echo json_encode($response);
    exit();
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;
$markAll = $input['mark_all'] ?? false;

try {
    $pdo = getPDOConnection();

    if ($markAll) {
        // Tandai semua notifikasi pengguna sebagai sudah dibaca
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        $response = ['status' => 'success', 'message' => 'All notifications marked as read.'];
    } elseif ($notificationId) {
        // Tandai notifikasi spesifik sebagai sudah dibaca
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        if ($stmt->rowCount() > 0) {
            $response = ['status' => 'success', 'message' => 'Notification marked as read.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Notification not found or already read.'];
        }
    } else {
        $response['message'] = 'Notification ID or mark_all flag is required.';
    }

} catch (PDOException $e) {
    error_log("API mark_notification_as_read Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API mark_notification_as_read General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
