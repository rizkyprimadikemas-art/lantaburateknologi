<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php'; // Pastikan jalur ini benar

$response = [
    'status' => 'error',
    'message' => 'Invalid request.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $macAddress = $input['mac_address'] ?? '';

    // Validasi MAC Address
    if (empty($macAddress)) {
        $response['message'] = 'MAC Address is required.';
        echo json_encode($response);
        exit();
    } elseif (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $macAddress)) {
        $response['message'] = 'Invalid MAC Address format.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDOConnection();
        
        // --- MODIFIKASI PENTING DI SINI ---
        // Cari perangkat berdasarkan device_id (MAC Address), TERMASUK yang sudah di-soft delete
        $stmt = $pdo->prepare("SELECT id, device_id, api_key, user_id, is_active, is_deleted FROM devices WHERE device_id = ?");
        $stmt->execute([$macAddress]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            // --- Perangkat Ditemukan (Bisa Aktif, Nonaktif, atau Soft Deleted) ---
            
            // Jika perangkat sebelumnya di-soft delete, aktifkan kembali
            if ($device['is_deleted'] == 1) {
                $updateStmt = $pdo->prepare("UPDATE devices SET is_deleted = 0, is_active = 1, is_online = 1, last_seen = NOW() WHERE id = ?");
                $updateStmt->execute([$device['id']]);
                
                $response = [
                    'status' => 'success',
                    'message' => 'Device reactivated and credentials retrieved.',
                    'device_id' => $device['device_id'],
                    'api_key' => $device['api_key']
                ];
            } 
            // Jika perangkat ada tapi tidak aktif (misal, user_id NULL atau is_active 0),
            // pastikan is_online dan last_seen diupdate
            else {
                $updateStmt = $pdo->prepare("UPDATE devices SET is_online = 1, last_seen = NOW() WHERE id = ?");
                $updateStmt->execute([$device['id']]);

                $response = [
                    'status' => 'success',
                    'message' => 'Device credentials retrieved.',
                    'device_id' => $device['device_id'],
                    'api_key' => $device['api_key']
                ];
            }

        } else {
            // --- Perangkat Belum Ditemukan Sama Sekali, Daftarkan yang Baru ---
            $newApiKey = bin2hex(random_bytes(32)); // Generate API Key baru
            $deviceName = "ESP32-" . substr(str_replace(':', '', $macAddress), -6); // Nama default (misal: ESP32-DB12EC)
            $deviceType = "Smart Meter"; // Tipe default
            
            // Atur user_id menjadi NULL dan is_active menjadi 0.
            // is_deleted = 0 karena ini perangkat baru.
            $userIdForNewDevice = NULL; 
            $isActiveForNewDevice = 0;

            $insertStmt = $pdo->prepare("INSERT INTO devices (user_id, device_name, device_id, device_type, api_key, is_active, is_deleted, is_online, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, 1, NOW())");
            $insertStmt->execute([$userIdForNewDevice, $deviceName, $macAddress, $deviceType, $newApiKey, $isActiveForNewDevice]);

            $response = [
                'status' => 'success',
                'message' => 'New device registered. It is currently unassigned and inactive. Please assign and activate it via the dashboard.',
                'device_id' => $macAddress,
                'api_key' => $newApiKey
            ];
        }

    } catch (PDOException $e) {
        error_log("API Error (get_device_credentials): " . $e->getMessage());
        $response['message'] = 'Database error.';
    } catch (Exception $e) {
        error_log("General Error (get_device_credentials): " . $e->getMessage());
        $response['message'] = 'Server error.';
    }
}

echo json_encode($response);
exit();
