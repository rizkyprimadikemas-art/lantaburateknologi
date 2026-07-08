<?php
// File: api/get_historical_data.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

$response = [
    'status' => 'error',
    'message' => 'Unauthorized',
    'labels' => [],
    'data' => []
];

// Pastikan pengguna login
if (!isLoggedIn()) {
    echo json_encode($response);
    exit();
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$device_db_id = $_GET['device_id'] ?? null;
$time_range = $_GET['time_range'] ?? '24h'; // Default 24 jam

if (!$device_db_id) {
    $response['message'] = 'Missing device ID.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getPDOConnection();

    // Verifikasi kepemilikan perangkat
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
    $stmt->execute([$device_db_id, $userId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $response['message'] = 'Device not found or not owned by user.';
        echo json_encode($response);
        exit();
    }

    $interval = '';
    $time_condition = '';
    $date_format = '';

    switch ($time_range) {
        case '24h':
            $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $interval = "HOUR";
            $date_format = "%H:00"; // Format jam:menit
            break;
        case '7d':
            $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $interval = "DAY";
            $date_format = "%a %d/%m"; // Format Hari Tgl/Bln
            break;
        case '30d':
            $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $interval = "DAY";
            $date_format = "%d/%m"; // Format Tgl/Bln
            break;
        default: // Default 24h
            $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $interval = "HOUR";
            $date_format = "%H:00";
            break;
    }

    // Query untuk mengambil rata-rata daya per interval waktu
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(created_at, ?) as label,
            AVG(power) as avg_power
        FROM energy_data
        WHERE device_id = ? AND {$time_condition}
        GROUP BY label
        ORDER BY label ASC
    ");
    $stmt->execute([$date_format, $device_db_id]);
    $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $dataPoints = [];

    foreach ($historicalData as $row) {
        $labels[] = $row['label'];
        $dataPoints[] = round($row['avg_power'], 2); // Bulatkan 2 angka di belakang koma
    }

    $response = [
        'status' => 'success',
        'labels' => $labels,
        'data' => $dataPoints
    ];

} catch (PDOException $e) {
    error_log("API Error (get_historical_data): " . $e->getMessage());
    $response['message'] = 'Database error.';
}

echo json_encode($response);
exit();
?>
