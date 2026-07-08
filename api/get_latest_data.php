<?php

// File: api/get_latest_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari mana saja (untuk pengembangan)
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Set zona waktu ke Asia/Jakarta untuk konsistensi
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
// Hapus: require_once __DIR__ . '/../config/electricity_tariffs.php'; // TIDAK LAGI DIGUNAKAN

$response = ['status' => 'error', 'message' => ''];

$deviceId = $_GET['device_id'] ?? null;
$requestingUserId = $_GET['user_id'] ?? null;

if (!$deviceId) {
    $response['message'] = 'Device ID is required.';
    echo json_encode($response);
    exit();
}
if (!$requestingUserId) {
    $response['message'] = 'User ID is required for authentication.';
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

    // Dapatkan detail perangkat untuk user_id DAN tarif_per_kwh dari tabel devices
    // MODIFIKASI: Tambahkan 'relay_state' ke SELECT statement
    $stmt = $pdo->prepare("SELECT user_id, tarif_per_kwh, relay_state FROM devices WHERE id = ? AND is_active = 1");
    $stmt->execute([$deviceId]);
    $deviceInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deviceInfo) {
        $response['message'] = 'Device not found or inactive.';
        echo json_encode($response);
        exit();
    }

    if ((int)$requestingUserId !== (int)$deviceInfo['user_id']) {
        $response['message'] = 'Unauthorized access to device data.';
        echo json_encode($response);
        exit();
    }

    // Ambil tarif_per_kwh dari tabel devices
    $tarifPerKwh = (float)($deviceInfo['tarif_per_kwh'] ?? 0);
    // Ambil relay_state dari deviceInfo
    $relayState = $deviceInfo['relay_state'] ?? 'off'; // Default 'off' jika tidak ada atau null

    $STALE_DATA_THRESHOLD_SECONDS = 100;
    $LOW_POWER_THRESHOLD_WATTS = 5.0; 

    // --- Helper function to get today's energy from raw data (MAX-MIN) ---
    function getTodayEnergyFromRawData(PDO $pdo, int $deviceId): float {
        $stmt = $pdo->prepare("
            SELECT (MAX(energy) - MIN(energy)) AS kwh_today
            FROM energy_data
            WHERE device_id = :device_id AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
        ");
        $stmt->execute([':device_id' => $deviceId]);
        return (float)($stmt->fetch(PDO::FETCH_ASSOC)['kwh_today'] ?? 0);
    }

    // --- DATA REAL-TIME ---
    $currentPower = 0;
    $currentVoltage = 0;
    $currentCurrent = 0;
    $currentMachineStatus = 'OFFLINE'; // Default status jika tidak ada data atau data basi
    $deviceStatus = 'offline'; // Default koneksi perangkat
    $lastDataCreatedAt = null;

    $stmt = $pdo->prepare("
        SELECT power, voltage, current, machine_status, created_at
        FROM energy_data
        WHERE device_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);
    $latestData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latestData) {
        $lastDataTimestamp = strtotime($latestData['created_at']);
        $currentTime = time();

        // Cek apakah data masih segar (dalam ambang batas)
        if (($currentTime - $lastDataTimestamp) <= $STALE_DATA_THRESHOLD_SECONDS) {
            $currentPower = (float)$latestData['power'];
            $currentVoltage = (float)$latestData['voltage'];
            $currentCurrent = (float)$latestData['current'];
            $deviceStatus = 'online'; // Jika ada data segar, perangkat dianggap online

            // --- LOGIC BARU UNTUK INFERENSI STATUS MESIN BERDASARKAN DAYA ---
            // Jika daya yang terdeteksi sangat rendah, override status mesin menjadi 'OFF'
            if ($currentPower < $LOW_POWER_THRESHOLD_WATTS) {
                $currentMachineStatus = 'OFF'; // Infer 'OFF' atau 'STANDBY'
            } else {
                // Jika daya di atas ambang batas, gunakan status yang dilaporkan oleh ESP
                $currentMachineStatus = $latestData['machine_status'];
            }
            // --- AKHIR LOGIC INFERENSI STATUS MESIN ---

        }
        $lastDataCreatedAt = $latestData['created_at'];
    }

    // --- ENERGI HARI INI & BIAYA HARI INI (Menggunakan MAX-MIN dari raw data) ---
    $dailyEnergyConsumption = getTodayEnergyFromRawData($pdo, $deviceId);
    $dailyCostConsumption = $dailyEnergyConsumption * $tarifPerKwh; // Menggunakan tarifPerKwh

    // --- ENERGI 7 HARI TERAKHIR & BIAYA 7 HARI TERAKHIR (Termasuk hari ini) ---
    $weeklyEnergyConsumption = 0;
    // Ambil data agregasi harian untuk 6 hari terakhir (kemarin hingga 7 hari lalu)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_energy_kwh), 0) AS aggregated_kwh
        FROM energy_data_daily
        WHERE device_id = :device_id
          AND date >= CURDATE() - INTERVAL 6 DAY
          AND date < CURDATE()
    ");
    $stmt->execute([':device_id' => $deviceId]);
    $weeklyEnergyConsumption += (float)($stmt->fetch(PDO::FETCH_ASSOC)['aggregated_kwh'] ?? 0);
    // Tambahkan konsumsi hari ini dari raw data
    $weeklyEnergyConsumption += $dailyEnergyConsumption;
    $weeklyCostConsumption = $weeklyEnergyConsumption * $tarifPerKwh; // Menggunakan tarifPerKwh

    // --- ENERGI BULAN INI & BIAYA BULAN INI ---
    $monthlyEnergyConsumption = 0;
    // Ambil data agregasi harian untuk hari-hari sebelumnya di bulan ini
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_energy_kwh), 0) AS aggregated_kwh
        FROM energy_data_daily
        WHERE device_id = :device_id
          AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND date < CURDATE()
    ");
    $stmt->execute([':device_id' => $deviceId]);
    $monthlyEnergyConsumption += (float)($stmt->fetch(PDO::FETCH_ASSOC)['aggregated_kwh'] ?? 0);
    // Tambahkan konsumsi hari ini dari raw data
    $monthlyEnergyConsumption += $dailyEnergyConsumption;
    $monthlyCostConsumption = $monthlyEnergyConsumption * $tarifPerKwh; // Menggunakan tarifPerKwh

    // --- ENERGI TOTAL (SELURUH WAKTU) ---
    $totalEnergyAllTime = 0;
    // Ambil data agregasi harian dari awal waktu hingga kemarin
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_energy_kwh), 0) AS aggregated_kwh
        FROM energy_data_daily
        WHERE device_id = :device_id
          AND date < CURDATE()
    ");
    $stmt->execute([':device_id' => $deviceId]);
    $totalEnergyAllTime += (float)($stmt->fetch(PDO::FETCH_ASSOC)['aggregated_kwh'] ?? 0);
    // Tambahkan konsumsi hari ini dari raw data
    $totalEnergyAllTime += $dailyEnergyConsumption;


    // --- Proyeksi Biaya Mingguan dan Bulanan ---
    $daysInMonth = (int)date('t'); // Jumlah hari dalam bulan ini
    $currentDayOfMonth = (int)date('d'); // Hari saat ini dalam bulan

    // Proyeksi biaya mingguan: berdasarkan konsumsi hari ini
    $weeklyCostProjection = round($dailyCostConsumption * 7, 0); // Menggunakan dailyCostConsumption

    // Proyeksi biaya bulanan: Menggunakan rata-rata konsumsi harian bulan ini (lebih akurat)
    $monthlyCostProjection = 0;
    $projectedKwhForMonth = 0;

    if ($currentDayOfMonth > 0 && $monthlyEnergyConsumption > 0) {
        // Jika sudah ada data bulan ini, proyeksikan total kWh bulan ini berdasarkan rata-rata harian
        $averageDailyKwhThisMonth = $monthlyEnergyConsumption / $currentDayOfMonth;
        $projectedKwhForMonth = $averageDailyKwhThisMonth * $daysInMonth;
    } else {
        // Jika belum ada data bulan ini (misal hari pertama bulan), proyeksikan total kWh bulan ini berdasarkan konsumsi hari ini
        $projectedKwhForMonth = $dailyEnergyConsumption * $daysInMonth;
    }

    // Hitung biaya dari proyeksi kWh. Fixed monthly charge tidak ditambahkan karena tidak ada di tarifPerKwh
    $monthlyCostProjection = $projectedKwhForMonth * $tarifPerKwh; // Menggunakan tarifPerKwh
    $monthlyCostProjection = round($monthlyCostProjection, 0);


    $response = [
        'status' => 'success',
        'latest_data' => [
            'power' => round($currentPower, 2),
            'voltage' => round($currentVoltage, 2),
            'current' => round($currentCurrent, 2),
            'machine_status' => $currentMachineStatus, // Ini yang sudah diinfer
            'device_status' => $deviceStatus, // Status koneksi ESP
            'created_at' => $lastDataCreatedAt ? date('d M Y H:i:s', strtotime($lastDataCreatedAt)) : 'Belum ada data', // Format timestamp
            'cumulative_energy' => round($totalEnergyAllTime, 3),
            'daily_energy_consumption' => round($dailyEnergyConsumption, 3),
            'daily_cost_consumption' => round($dailyCostConsumption, 0),
            'weekly_energy_consumption' => round($weeklyEnergyConsumption, 3),
            'weekly_cost_consumption' => round($weeklyCostConsumption, 0),
            'monthly_energy_consumption' => round($monthlyEnergyConsumption, 3),
            'monthly_cost_consumption' => round($monthlyCostConsumption, 0),
            'weekly_cost_projection' => $weeklyCostProjection,
            'monthly_cost_projection' => $monthlyCostProjection,
            'relay_state' => $relayState // MODIFIKASI: Tambahkan relay_state di sini
              ]
    ];

} catch (PDOException $e) {
    error_log("API get_latest_data Error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API get_latest_data General Error: " . $e->getMessage());
    $response['message'] = 'General error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
