<?php
// File: api/get_daily_summary.php
header('Content-Type: application/json');

// Memastikan sesi dimulai jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Memuat helper autentikasi dan konfigurasi database
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Jakarta'); // Sesuaikan ini jika zona waktu server database Anda berbeda

$response = [
    'status' => 'error',
    'message' => 'Unauthorized'
];

// Pastikan pengguna sudah login
if (!isLoggedIn()) {
    echo json_encode($response);
    exit();
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$device_db_id = $_GET['device_id'] ?? null;

// Validasi apakah device_id diberikan
if (!$device_db_id) {
    $response['message'] = 'Missing device ID.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getPDOConnection();

    // 1. Verifikasi kepemilikan perangkat dan ambil tarif_per_kwh
    $stmt = $pdo->prepare("SELECT tarif_per_kwh FROM devices WHERE id = ? AND user_id = ?");
    $stmt->execute([$device_db_id, $userId]);
    $deviceInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deviceInfo) {
        $response['message'] = 'Device not found or not owned by user.';
        echo json_encode($response);
        exit();
    }

    $tarifPerKwh = (float) $deviceInfo['tarif_per_kwh'];

    // 2. Hitung total kWh untuk hari ini
    $today = date('Y-m-d');
    $startOfDay = $today . ' 00:00:00';
    $endOfDay = $today . ' 23:59:59';

    // Ambil pembacaan energi pertama di hari ini (atau pembacaan terakhir sebelum hari ini jika tidak ada data pas 00:00)
    // Ini penting untuk meter energi kumulatif
    $startEnergy = 0.0;

    // Coba ambil pembacaan energi terakhir dari hari sebelumnya
    $stmtFirstBeforeToday = $pdo->prepare("
        SELECT energy FROM energy_data
        WHERE device_id = ? AND created_at < ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtFirstBeforeToday->execute([$device_db_id, $startOfDay]);
    $firstReadingBeforeToday = $stmtFirstBeforeToday->fetch(PDO::FETCH_ASSOC);

    if ($firstReadingBeforeToday) {
        $startEnergy = (float) $firstReadingBeforeToday['energy'];
    }

    // Ambil pembacaan energi pertama di hari ini
    $stmtFirstToday = $pdo->prepare("
        SELECT energy FROM energy_data
        WHERE device_id = ? AND created_at >= ? AND created_at <= ?
        ORDER BY created_at ASC LIMIT 1
    ");
    $stmtFirstToday->execute([$device_db_id, $startOfDay, $endOfDay]);
    $firstReadingToday = $stmtFirstToday->fetch(PDO::FETCH_ASSOC);

    // Jika ada data di hari ini, gunakan pembacaan pertama hari ini sebagai startEnergy
    if ($firstReadingToday) {
        $startEnergy = (float) $firstReadingToday['energy'];
    }


    // Ambil pembacaan energi terakhir di hari ini
    $stmtLastToday = $pdo->prepare("
        SELECT energy FROM energy_data
        WHERE device_id = ? AND created_at >= ? AND created_at <= ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtLastToday->execute([$device_db_id, $startOfDay, $endOfDay]);
    $lastReadingToday = $stmtLastToday->fetch(PDO::FETCH_ASSOC);

    $totalKwhToday = 0.0;
    if ($lastReadingToday) {
        $endEnergy = (float) $lastReadingToday['energy'];
        // Pastikan endEnergy tidak lebih kecil dari startEnergy (bisa terjadi jika meter reset atau error data)
        if ($endEnergy >= $startEnergy) {
            $totalKwhToday = $endEnergy - $startEnergy;
        } else {
           if ($startEnergy == 0) {
                $totalKwhToday = $endEnergy;
            } else {
                $totalKwhToday = 0.0; // Asumsi 0 jika anomali
            }
        }
    }

    $estimatedCostToday = $totalKwhToday * $tarifPerKwh;

    $response = [
        'status' => 'success',
        'total_kwh_today' => $totalKwhToday,
        'estimated_cost_today' => $estimatedCostToday,
        'tarif_per_kwh' => $tarifPerKwh
    ];

} catch (PDOException $e) {
    error_log("API Error (get_daily_summary): " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
