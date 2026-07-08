<?php
// File: api/get_device_chart_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => ''];

$deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
$chartType = $_GET['chart_type'] ?? 'realtime_power'; // Default ke realtime_power

if ($deviceId <= 0) {
    $response['message'] = 'Invalid device ID provided.';
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

    $labels = [];
    $dataPoints = [];

    switch ($chartType) {
        case 'realtime_power':
            // Ambil data daya per menit untuk 24 jam terakhir
            $stmt = $pdo->prepare("
                SELECT
                    DATE_FORMAT(created_at, '%H:%i') AS time_label,
                    AVG(power) AS avg_power
                FROM energy_data
                WHERE device_id = ?
                  AND created_at >= NOW() - INTERVAL 24 HOUR
                GROUP BY time_label
                ORDER BY created_at ASC;
            ");
            $stmt->execute([$deviceId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                foreach ($results as $row) {
                    $labels[] = $row['time_label'];
                    $dataPoints[] = round((float)$row['avg_power'], 2);
                }
                $response['status'] = 'success';
                $response['labels'] = $labels;
                $response['data'] = $dataPoints;
            } else {
                $response['message'] = 'No power data available for the last 24 hours.';
            }
            break;
        // Anda bisa menambahkan case lain di sini untuk chart type lain (misal: daily_energy, monthly_cost)
        default:
            $response['message'] = 'Unsupported chart type.';
            break;
    }

} catch (PDOException $e) {
    error_log("API get_device_chart_data.php Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API get_device_chart_data.php General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
