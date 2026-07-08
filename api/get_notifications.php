<?php
// File: api/get_notifications.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

$filter = $_GET['filter'] ?? 'all'; // 'all' or 'unread'

try {
    $pdo = getPDOConnection();

    $sql = "
        SELECT
            n.id,
            n.type,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            d.device_name
        FROM notifications n
        LEFT JOIN devices d ON n.device_id = d.id
        WHERE n.user_id = ?
    ";
    $params = [$userId];

    if ($filter === 'unread') {
        $sql .= " AND n.is_read = FALSE";
    }

    $sql .= " ORDER BY n.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'status' => 'success',
        'notifications' => $notifications
    ];

} catch (PDOException $e) {
    error_log("API get_notifications Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API get_notifications General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
