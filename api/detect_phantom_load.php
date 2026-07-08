<?php
// File: api/detect_phantom_load.php
// Skrip ini dirancang untuk dijalankan sebagai cron job.
// Tidak memerlukan sesi login karena berjalan di latar belakang.

// PENTING: Skrip ini mengasumsikan program ESP Anda telah dimodifikasi
// untuk mengirimkan daya aktual meskipun machine_status adalah 'OFF'.
// Perubahan di ESP:
// Dari: float instantaneousPower = isMachineOn ? smoothVoltage * smoothCurrent : 0;
// Menjadi: float instantaneousPower = smoothVoltage * smoothCurrent;

// Set zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
// Asumsikan Anda memiliki fungsi untuk mengirim notifikasi.
// Untuk saat ini, kita akan menggunakan error_log, nanti bisa diganti dengan notifikasi WhatsApp/Push.
// require_once __DIR__ . '/../includes/notification_helper.php'; // Akan dibuat di Fase 4

// --- Konfigurasi Deteksi Kebocoran ---
// Ambang batas daya minimum (dalam Watt) untuk dianggap sebagai "kebocoran" saat perangkat OFF.
// Ini adalah nilai fallback jika estimated_standby_power_w tidak disetel per perangkat.
const DEFAULT_PHANTOM_LOAD_THRESHOLD_WATT = 0.5; // Contoh: 0.5 Watt
const CHECK_INTERVAL_MINUTES = 15; // Seberapa sering cron job ini dijalankan, dan berapa lama data yang diperiksa
const MIN_DURATION_FOR_ALERT_MINUTES = 5; // Minimal berapa lama kondisi phantom load berlangsung untuk memicu alert
const ALERT_COOLDOWN_MINUTES = 60; // Jangan kirim alert baru untuk perangkat yang sama dalam periode ini (untuk mencegah spam)

try {
    $pdo = getPDOConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Aktifkan mode error PDO

    // Waktu mulai dan akhir untuk memeriksa data terbaru
    $endTime = new DateTime();
    $startTime = (clone $endTime)->modify("-".CHECK_INTERVAL_MINUTES." minutes");

    error_log("Phantom Load Detector: Checking data from " . $startTime->format('Y-m-d H:i:s') . " to " . $endTime->format('Y-m-d H:i:s'));

    // Query untuk menemukan perangkat yang statusnya 'OFF' tapi dayanya di atas ambang batas.
    // Menggunakan estimated_standby_power_w dari tabel devices sebagai ambang batas dinamis.
    $sql = "
        SELECT
            ed.device_id,
            d.device_name,
            d.user_id,
            u.full_name AS user_name,
            u.email AS user_email,
            AVG(ed.power) AS avg_power_during_off, -- Menggunakan AVG daripada MAX untuk representasi yang lebih baik
            MIN(ed.created_at) AS first_detection_time,
            MAX(ed.created_at) AS last_detection_time,
            COUNT(ed.id) AS num_readings_during_off,
            -- Ambil ambang batas phantom load dari tabel devices, gunakan default jika NULL
            COALESCE(d.estimated_standby_power_w, :default_phantom_threshold) AS device_phantom_load_threshold
        FROM energy_data ed
        JOIN devices d ON ed.device_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE
            ed.created_at >= ? AND ed.created_at <= ? AND
            ed.machine_status = 'OFF' AND
            d.is_active = 1
        GROUP BY ed.device_id, d.device_name, d.user_id, u.full_name, u.email, d.estimated_standby_power_w
        HAVING
            avg_power_during_off >= device_phantom_load_threshold AND -- Bandingkan dengan ambang batas dinamis per perangkat
            num_readings_during_off >= ? -- Asumsi ESP kirim data setiap 1 menit, jadi ini adalah jumlah minimum pembacaan
    ";

    // CATATAN PENTING: Pastikan kolom 'device_id' dan 'created_at' di tabel 'energy_data'
    // memiliki INDEX untuk performa query yang optimal.

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'default_phantom_threshold' => DEFAULT_PHANTOM_LOAD_THRESHOLD_WATT,
        $startTime->format('Y-m-d H:i:s'),
        $endTime->format('Y-m-d H:i:s'),
        MIN_DURATION_FOR_ALERT_MINUTES // Jika ESP mengirim data setiap 1 menit, ini adalah jumlah minimum entri yang diperlukan
    ]);

    $phantomLoadDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($phantomLoadDevices)) {
        foreach ($phantomLoadDevices as $device) {
            // Hitung durasi kondisi ini
            $firstTime = new DateTime($device['first_detection_time']);
            $lastTime = new DateTime($device['last_detection_time']);
            $duration = $firstTime->diff($lastTime)->i; // Durasi dalam menit

            // Hanya kirim notifikasi jika durasi memenuhi ambang batas
            if ($duration >= MIN_DURATION_FOR_ALERT_MINUTES) {
                // Pencegahan Duplikasi Peringatan:
                // Cek apakah sudah ada peringatan phantom_load untuk perangkat ini
                // dalam periode ALERT_COOLDOWN_MINUTES terakhir.
                $sqlCheckExistingAlert = "
                    SELECT COUNT(*) FROM alerts
                    WHERE device_id = ? AND type = 'phantom_load' AND created_at >= ?
                ";
                $stmtCheckExistingAlert = $pdo->prepare($sqlCheckExistingAlert);
                $stmtCheckExistingAlert->execute([
                    $device['device_id'],
                    (clone $endTime)->modify("-".ALERT_COOLDOWN_MINUTES." minutes")->format('Y-m-d H:i:s')
                ]);
                $existingAlertCount = $stmtCheckExistingAlert->fetchColumn();

                if ($existingAlertCount == 0) { // Jika belum ada peringatan baru dalam periode cooldown
                    $message = "Deteksi Kebocoran/Daya Siaga! Perangkat '{$device['device_name']}' (ID: {$device['device_id']}) terdeteksi mengonsumsi daya rata-rata " . round($device['avg_power_during_off'], 3) . " Watt saat berstatus OFF (ambang batas: " . round($device['device_phantom_load_threshold'], 3) . " Watt). Ini berlangsung selama sekitar {$duration} menit.";
                        
                    // Simpan peringatan ke database
                    $insertAlertSql = "
                        INSERT INTO alerts (user_id, device_id, type, message, severity)
                        VALUES (?, ?, ?, ?, ?)
                    ";
                    $stmtInsertAlert = $pdo->prepare($insertAlertSql);
                    $stmtInsertAlert->execute([
                        $device['user_id'],
                        $device['device_id'],
                        'phantom_load', // Tipe peringatan
                        $message,
                        'warning' // Tingkat keparahan
                    ]);
                    error_log("PHANTOM_LOAD_ALERT saved for User {$device['user_name']} ({$device['user_email']}): " . $message);
                   
                    // --- Nanti di Fase 4, Anda bisa menambahkan fungsi notifikasi di sini ---
                    // sendNotification($device['user_id'], $device['user_email'], $message, 'phantom_load');
                } else {
                    error_log("Phantom Load Detector: Skipping alert for device {$device['device_name']} (ID: {$device['device_id']}) due to recent alert within cooldown period.");
                }
            }
        }
    } else {
        error_log("Phantom Load Detector: No phantom load detected in the last " . CHECK_INTERVAL_MINUTES . " minutes.");
    }

} catch (PDOException $e) {
    error_log("Phantom Load Detector Error: " . $e->getMessage());
}
?>
