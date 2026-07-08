<?php
// File: api/get_dashboard_chart_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$response = ['status' => 'error', 'message' => ''];

// Ambil user_id dari parameter GET
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    $response['message'] = 'Invalid user ID';
    echo json_encode($response);
    exit();
}

$chartType = $_GET['chart_type'] ?? 'realtime_power';
// $deviceId is not used for aggregate charts, but kept for flexibility.

try {
    $pdo = getPDOConnection();
    
    if (!$pdo) {
        $response['message'] = 'Database connection failed';
        echo json_encode($response);
        exit();
    }

    // Get active device IDs for the user
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $deviceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($deviceIds)) {
        echo json_encode([
            'status' => 'success',
            'labels' => [],
            'data' => [],
            'message' => 'No active devices found for this user.'
        ]);
        exit();
    }
    $inClause = implode(',', array_fill(0, count($deviceIds), '?'));


    if ($chartType === 'realtime_power') {
        // Generate all possible hour labels for the last 24 hours
        $allLabels = [];
        $now = new DateTime();
        for ($i = 23; $i >= 0; $i--) {
            $hour = clone $now;
            $hour->modify("-{$i} hour");
            $allLabels[] = $hour->format('H:00');
        }

        // This query does NOT use LAG(), so it should be fine.
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(ed.created_at, '%H:00') AS label,
                ROUND(AVG(ed.power), 2) AS value
            FROM energy_data ed
            INNER JOIN devices d ON ed.device_id = d.id
            WHERE d.user_id = ? AND d.is_active = 1
                AND ed.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY DATE_FORMAT(ed.created_at, '%Y-%m-%d %H:00')
            ORDER BY MIN(ed.created_at) ASC
        ");
        $stmt->execute([$userId]); // Use $userId directly here as it's aggregated
        $fetchedData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dataMap = [];
        foreach ($fetchedData as $row) {
            $dataMap[$row['label']] = (float)$row['value'];
        }

        $values = [];
        foreach ($allLabels as $label) {
            $values[] = $dataMap[$label] ?? 0; // Fill with 0 if no data for that hour
        }

        $response = [
            'status' => 'success',
            'labels' => $allLabels,
            'data' => $values
        ];

    } elseif ($chartType === 'daily_energy') {
        // Generate all possible date labels for the last 7 days
        $allLabels = [];
        $today = new DateTime();
        for ($i = 6; $i >= 0; $i--) { // Loop for 7 days (today and 6 previous days)
            $date = clone $today;
            $allLabels[] = $date->modify("-{$i} day")->format('Y-m-d');
        }

        $aggregatedDailyEnergy = []; // [date_str] => total_energy_for_all_devices_on_that_day

        // --- MODIFIKASI DIMULAI DI SINI ---

        // 1. Ambil data dari energy_data_daily untuk hari-hari sebelumnya (kemarin hingga 6 hari lalu)
        if (!empty($deviceIds)) {
            $stmt = $pdo->prepare("
                SELECT
                    eh.date AS tgl,
                    SUM(eh.total_energy_kwh) AS total_kwh_for_day
                FROM energy_data_daily eh
                WHERE eh.device_id IN ({$inClause})
                    AND eh.date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                    AND eh.date < CURDATE() -- Hanya sampai kemarin
                GROUP BY eh.date
                ORDER BY eh.date ASC
            ");
            $stmt->execute($deviceIds);
            $dailyAggregatedData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dailyAggregatedData as $row) {
                $aggregatedDailyEnergy[$row['tgl']] = (float)$row['total_kwh_for_day'];
            }
        }

        // 2. Ambil data dari energy_data mentah untuk HARI INI saja
        // Menggunakan logika perhitungan PHP yang ada untuk mengatasi meter reset
        $rawEnergyDataToday = [];
        if (!empty($deviceIds)) {
            $stmt = $pdo->prepare("
                SELECT
                    ed.device_id,
                    DATE(ed.created_at) AS tgl,
                    ed.energy,
                    ed.created_at
                FROM energy_data ed
                WHERE ed.device_id IN ({$inClause})
                    AND ed.created_at >= CURDATE() -- Hanya untuk hari ini
                ORDER BY ed.device_id, ed.created_at ASC
            ");
            $stmt->execute($deviceIds);
            $rawEnergyDataToday = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $dailyEnergyPerDeviceToday = []; // [device_id] => [readings]
        foreach ($rawEnergyDataToday as $row) {
            $dailyEnergyPerDeviceToday[$row['device_id']][] = (float)$row['energy'];
        }

        $totalEnergyForToday = 0;
        foreach ($dailyEnergyPerDeviceToday as $devId => $readings) {
            $deviceDailyConsumption = 0;
            if (count($readings) > 1) {
                for ($i = 1; $i < count($readings); $i++) {
                    $diff = $readings[$i] - $readings[$i-1];
                    if ($diff > 0) { // Hanya jumlahkan kenaikan positif
                        $deviceDailyConsumption += $diff;
                    }
                }
            } elseif (count($readings) == 1) {
                // Jika hanya ada satu pembacaan untuk hari ini, tidak bisa menghitung delta.
                // Asumsikan konsumsi 0 untuk hari itu dari data mentah jika hanya ada 1 titik.
                // Agregasi harian akan menangani ini dengan MAX-MIN.
                $deviceDailyConsumption = 0; 
            }
            $totalEnergyForToday += $deviceDailyConsumption;
        }
        $aggregatedDailyEnergy[$today->format('Y-m-d')] = $totalEnergyForToday;


        // --- MODIFIKASI BERAKHIR DI SINI ---

        $labels = [];
        $values = [];
        foreach ($allLabels as $label) {
            $labels[] = $label;
            $values[] = $aggregatedDailyEnergy[$label] ?? 0;
        }

        $response = [
            'status' => 'success',
            'labels' => $labels,
            'data' => $values
        ];
    } else {
        $response['message'] = 'Invalid chart type';
    }

} catch (PDOException $e) {
    error_log("Chart Data Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("Chart Data General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
