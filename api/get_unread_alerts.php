<?php
// File: api/get_unread_alerts.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => ''];

$userId = $_GET['user_id'] ?? null;

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

    // 1. Dapatkan jumlah notifikasi yang belum dibaca
    $stmt = $pdo->prepare("SELECT COUNT(*) AS unread_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

    // 2. Dapatkan beberapa notifikasi terbaru yang belum dibaca (misal 5 notifikasi)
    $stmt = $pdo->prepare("SELECT id, message, created_at, severity FROM alerts WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentUnreadAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format created_at
    foreach ($recentUnreadAlerts as &$alert) {
        $alert['created_at_formatted'] = date('d/m/Y H:i', strtotime($alert['created_at']));
    }
    unset($alert); // Putuskan referensi terakhir

    $response = [
        'status' => 'success',
        'unread_count' => $unreadCount,
        'recent_alerts' => $recentUnreadAlerts
    ];

} catch (PDOException $e) {
    error_log("API get_unread_alerts Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API get_unread_alerts General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
