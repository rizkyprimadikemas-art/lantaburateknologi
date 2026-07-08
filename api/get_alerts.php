<?php
// File: api/get_alerts.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => ''];

$userId = $_GET['user_id'] ?? null;
$limit = $_GET['limit'] ?? 5; // Default 5 alerts
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true'; // Filter hanya yang belum dibaca

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

    $sql = "SELECT a.id, a.message, a.created_at, a.severity, a.is_read, d.device_name
            FROM alerts a
            LEFT JOIN devices d ON a.device_id = d.id
            WHERE a.user_id = ?";
    $params = [$userId];

    if ($unreadOnly) {
        $sql .= " AND a.is_read = 0";
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT ?";
    $params[] = (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dapatkan juga jumlah total notifikasi yang belum dibaca (untuk badge di dashboard utama)
    $stmtUnreadCount = $pdo->prepare("SELECT COUNT(*) AS unread_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $stmtUnreadCount->execute([$userId]);
    $unreadCount = $stmtUnreadCount->fetch(PDO::FETCH_ASSOC)['unread_count'];

    // Format created_at
    foreach ($alerts as &$alert) {
        $alert['created_at_formatted'] = date('d/m/Y H:i', strtotime($alert['created_at']));
    }
    unset($alert);

    $response = [
        'status' => 'success',
        'alerts' => $alerts,
        'unread_count' => $unreadCount // Tambahkan ini untuk badge di dashboard utama
    ];

} catch (PDOException $e) {
    error_log("API get_alerts Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API get_alerts General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
