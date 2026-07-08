<?php
// File: api/receive_data.php
header('Content-Type: application/json');

// Memastikan bahwa file database.php di-include untuk koneksi database
require_once __DIR__ . '/../config/database.php';

$response = [
    'status' => 'error',
    'message' => 'Invalid request'
];

// Logging
function writeLog($message) {
    $logFile = __DIR__ . '/../logs/receive_data.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== Request received ===");

// Pastikan request yang diterima adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil body request JSON
    $input = json_decode(file_get_contents('php://input'), true);
    writeLog("Input: " . print_r($input, true));

    // Mengambil data dari input JSON
    $deviceId = $input['device_id'] ?? '';
    $apiKey = $input['api_key'] ?? ''; // Tetap ambil tapi tidak digunakan untuk validasi
    $power = $input['power'] ?? null;
    $maxPowerInterval = $input['max_power_interval'] ?? null; // <<< MODIFIKASI: Ambil max_power_interval
    $voltage = $input['voltage'] ?? null;
    $current = $input['current'] ?? null;
    $energy = $input['energy'] ?? null;
    $machineStatus = $input['machine_status'] ?? 'UNKNOWN';
    $deviceStatus = $input['device_status'] ?? 'offline';
    $relayState = $input['relay_state'] ?? 'off'; // <<< Tambahkan relay_state

    writeLog("Parsed - device_id: $deviceId, api_key: $apiKey, power: $power, max_power_interval: $maxPowerInterval, voltage: $voltage, current: $current, energy: $energy, machine_status: $machineStatus, device_status: $deviceStatus, relay_state: $relayState"); // <<< MODIFIKASI: Tambahkan ke log

    // Validasi dasar - hanya device_id yang wajib
    if (empty($deviceId)) {
        $response['message'] = 'Missing device_id';
        echo json_encode($response);
        exit();
    }

    // <<< MODIFIKASI: Tambahkan maxPowerInterval ke validasi
    if (!isset($power) || !isset($maxPowerInterval) || !isset($voltage) || !isset($current) || !isset($energy)) {
        $response['message'] = 'Missing required data (power, max_power_interval, voltage, current, energy).';
        echo json_encode($response);
        exit();
    }

    try {
        // Mendapatkan koneksi PDO
        $pdo = getPDOConnection();
        writeLog("Database connected");

        // --- PERUBAHAN: Verifikasi HANYA berdasarkan device_id (MAC address) ---
        // API Key diabaikan sepenuhnya
        $stmt = $pdo->prepare("SELECT id, is_active, user_id FROM devices WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            writeLog("Device not found. device_id: $deviceId");
            $response['message'] = 'Device not registered.';
            echo json_encode($response);
            exit();
        }

        $deviceDbId = $device['id'];
        $isActive = $device['is_active'];
        $userId = $device['user_id'];
        
        writeLog("Device found with DB ID: $deviceDbId, is_active: $isActive, user_id: " . ($userId ?? 'NULL'));

        // --- Data tetap disimpan ---
        // <<< MODIFIKASI: Tambahkan kolom max_power ke INSERT statement
        $insertStmt = $pdo->prepare("
            INSERT INTO energy_data (device_id, power, max_power, voltage, current, energy, machine_status, device_status, relay_state, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        // <<< MODIFIKASI: Tambahkan $maxPowerInterval dan $relayState ke execute array
        $insertStmt->execute([$deviceDbId, $power, $maxPowerInterval, $voltage, $current, $energy, $machineStatus, $deviceStatus, $relayState]);
        
        $insertId = $pdo->lastInsertId();
        writeLog("Energy data inserted. ID: $insertId");

        // Update status perangkat dan relay_state
        // <<< MODIFIKASI: Tambahkan relay_state ke UPDATE statement
        $updateStmt = $pdo->prepare("UPDATE devices SET is_online = 1, last_seen = NOW(), relay_state = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$relayState, $deviceDbId]);
        
        writeLog("Device status and relay_state updated");

        $response = [
            'status' => 'success',
            'message' => 'Energy data received and stored.',
            'data_id' => $insertId,
            'device_active' => ($isActive == 1) // Beri tahu ESP32 apakah perangkat sudah aktif
        ];

    } catch (PDOException $e) {
        writeLog("Database error: " . $e->getMessage());
        error_log("API Error (receive_data): " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Only POST requests are allowed.';
}

echo json_encode($response);
exit();
?>
