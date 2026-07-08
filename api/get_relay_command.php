<?php
// File: api/get_relay_command.php
// Bertanggung jawab untuk memberikan perintah ON/OFF relay kepada ESP32 berdasarkan logika EMS.

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta'); // Pastikan zona waktu sesuai dengan lokasi Anda

// Pastikan path ke database.php benar. Sesuaikan jika struktur folder Anda berbeda.
require_once __DIR__ . '/../config/database.php'; 

$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'command' => 'no_change' // Default command jika tidak ada logika yang berlaku
];

// Fungsi logging untuk debugging. Akan menyimpan log di folder 'logs' di root project.
function writeRelayLog($message) {
    $logFile = __DIR__ . '/../logs/relay_commands.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true); // Buat direktori jika belum ada
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeRelayLog("=== Relay command request received ===");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $esp32_device_id = $input['device_id'] ?? ''; // Ini adalah MAC address dari ESP32
    $api_key = $input['api_key'] ?? ''; // Diambil, tapi tidak digunakan untuk validasi utama (sesuai receive_data.php)

    writeRelayLog("Request from device_id: {$esp32_device_id}");

    if (empty($esp32_device_id)) {
        $response['message'] = 'Missing device_id in request.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDOConnection();

        // 1. Autentikasi Perangkat dan Ambil Pengaturannya
        // Ambil semua pengaturan relevan untuk logika kontrol relay dari tabel 'devices'.
        // Kolom-kolom ini harus ada di tabel 'devices' Anda.
        $stmt = $pdo->prepare("
            SELECT
                id, device_name, is_active, user_id, relay_state,
                auto_shutdown_standby, standby_threshold_watt, standby_detection_duration_minutes,
                auto_shutdown_overload
            FROM devices
            WHERE device_id = ?
        ");
        $stmt->execute([$esp32_device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            writeRelayLog("Device not found for device_id: {$esp32_device_id}");
            $response['message'] = 'Device not registered.';
            echo json_encode($response);
            exit();
        }

        $device_db_id = $device['id'];
        $current_desired_relay_state = $device['relay_state']; // Status relay yang terakhir diinginkan oleh server

        // Jika perangkat tidak aktif (admin menonaktifkan), paksa relay OFF
        if (!$device['is_active']) {
            writeRelayLog("Device '{$device['device_name']}' (ID: {$device_db_id}) is inactive. Forcing OFF.");
            $command_to_send = 'off';
            $reason = "Device inactive";
        } else {
            // Inisialisasi perintah dengan status terakhir yang diinginkan dari DB.
            // Ini adalah status "default" jika tidak ada logika lain yang memicu perubahan.
            $command_to_send = $current_desired_relay_state;
            $reason = "Default (last known desired state: {$current_desired_relay_state})";

            // --- Logika Prioritas ---
            // Prioritas ditentukan dari atas ke bawah. Yang lebih tinggi akan menimpa yang lebih rendah.

            // P1: Penjadwalan Otomatis (Prioritas Tertinggi)
            // Memeriksa apakah ada jadwal aktif untuk perangkat pada waktu dan hari saat ini.
            $current_time = date('H:i:s'); // Waktu saat ini (HH:MM:SS)
            // Bitmask untuk hari saat ini: 1=Senin (1), 2=Selasa (2), 4=Rabu (4), ..., 64=Minggu (7)
            $current_day_of_week_bit = pow(2, (date('N') - 1)); 

            $stmtSchedule = $pdo->prepare("
                SELECT action
                FROM device_schedules
                WHERE
                    device_id = ? AND is_active = 1
                    AND start_time <= ? AND end_time >= ?
                    AND (repeat_days & ?) > 0 -- Cek apakah bit hari ini aktif di repeat_days
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtSchedule->execute([$device_db_id, $current_time, $current_time, $current_day_of_week_bit]);
            $schedule = $stmtSchedule->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                // Jika ada jadwal aktif, perintah dari jadwal akan digunakan.
                $command_to_send = $schedule['action'];
                $reason = "Scheduled control ({$schedule['action']})";
                writeRelayLog("Scheduled command '{$command_to_send}' for device '{$device['device_name']}'.");
            } else {
                // P2: Pemutusan Overload Otomatis (Jika tidak ada jadwal aktif)
                // Logika ini bergantung pada `detect_anomaly.php` yang memperbarui `devices.relay_state` menjadi 'off'
                // jika anomali kritis terdeteksi dan `auto_shutdown_overload` aktif.
                // Jadi, kita hanya perlu mematuhi `relay_state` di DB jika `auto_shutdown_overload` aktif
                // dan `relay_state` sudah 'off'.
                if ($device['auto_shutdown_overload'] && $current_desired_relay_state == 'off') {
                    // Ini adalah skenario di mana detect_anomaly.php sudah mematikan relay.
                    // Kita pertahankan command_to_send sebagai 'off'.
                    $command_to_send = 'off'; // Pastikan command_to_send adalah 'off'
                    $reason = "Overload shutdown (set by anomaly detector)";
                    writeRelayLog("Overload shutdown command 'off' for device '{$device['device_name']}'.");
                }
                
                // P3: Pemutusan Perangkat Standby (Jika tidak ada jadwal aktif dan tidak dalam kondisi overload shutdown)
                // Logika ini hanya berjalan jika `command_to_send` belum diubah oleh logika sebelumnya
                // dan fitur `auto_shutdown_standby` diaktifkan.
                if ($command_to_send == $current_desired_relay_state && $device['auto_shutdown_standby']) {
                    $standby_threshold_watt = (float)$device['standby_threshold_watt'];
                    $standby_detection_duration_minutes = (int)$device['standby_detection_duration_minutes'];

                    // Ambil data daya rata-rata untuk durasi standby yang ditentukan.
                    // Cek apakah perangkat konsisten OFF (menurut ESP32) tapi masih ada konsumsi daya.
                    $stmtStandby = $pdo->prepare("
                        SELECT
                            AVG(power) as avg_power,
                            MAX(CASE WHEN machine_status = 'ON' THEN 1 ELSE 0 END) as was_on_in_period
                        FROM energy_data
                        WHERE
                            device_id = ?
                            AND created_at >= NOW() - INTERVAL ? MINUTE
                    ");
                    $stmtStandby->execute([$device_db_id, $standby_detection_duration_minutes]);
                    $standbyData = $stmtStandby->fetch(PDO::FETCH_ASSOC);

                    // Ambang batas daya minimal untuk dianggap standby (bukan benar-benar OFF).
                    // Misalnya, di bawah 2 Watt mungkin dianggap noise atau sensor mati.
                    $min_power_for_standby_check = 2.0; 

                    if ($standbyData && $standbyData['was_on_in_period'] == 0 && $standbyData['avg_power'] > $min_power_for_standby_check && $standbyData['avg_power'] < $standby_threshold_watt) {
                        // Perangkat konsumsi daya rendah (standby) dan ESP32 melaporkan OFF secara konsisten.
                        // Jika `relay_state` saat ini adalah 'on', maka kita matikan.
                        if ($command_to_send == 'on') {
                            $command_to_send = 'off';
                            $reason = "Standby power detected (Avg Power: " . round($standbyData['avg_power'], 2) . "W < Threshold: {$standby_threshold_watt}W for {$standby_detection_duration_minutes} min)";
                            writeRelayLog("Standby shutdown command 'off' for device '{$device['device_name']}'.");
                        }
                    }
                }
            }
        }
        
        // Final: Perbarui status relay di database jika ada perubahan yang diinginkan.
        // Ini memastikan bahwa `relay_state` di DB selalu mencerminkan perintah terakhir yang dikirim.
        if ($command_to_send !== $current_desired_relay_state) {
            $updateStmt = $pdo->prepare("
                UPDATE devices
                SET relay_state = ?, last_relay_command_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$command_to_send, $device_db_id]);
            writeRelayLog("Updated device '{$device['device_name']}' relay_state to '{$command_to_send}' (Reason: {$reason}).");
        } else {
            writeRelayLog("Device '{$device['device_name']}' relay_state remains '{$command_to_send}'. No change needed.");
        }

        $response = [
            'status' => 'success',
            'message' => 'Relay command determined.',
            'command' => $command_to_send // Kirim perintah yang telah ditentukan ke ESP32
        ];

    } catch (PDOException $e) {
        writeRelayLog("Database error: " . $e->getMessage());
        // Untuk produksi, sebaiknya jangan tampilkan pesan error detail ke publik.
        error_log("API Error (get_relay_command): " . $e->getMessage()); 
        $response['message'] = 'Database error occurred.'; // Pesan error yang lebih umum
    }
} else {
    $response['message'] = 'Only POST requests are allowed.';
}

echo json_encode($response);
exit();
