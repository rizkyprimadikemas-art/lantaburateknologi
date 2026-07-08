<?php
// Script ini dirancang untuk dijalankan dari command line atau cron job/task scheduler.
// Ini akan mengagregasi data energy_data mentah ke tabel agregasi per jam dan per hari,
// serta menghapus data lama sesuai kebijakan retensi.

// Set zona waktu ke Asia/Jakarta untuk konsistensi waktu
date_default_timezone_set('Asia/Jakarta');

// Sertakan file koneksi database
require_once __DIR__ . '/../config/database.php';

echo "Starting data maintenance script...\n";

try {
    $pdo = getPDOConnection();
    // Aktifkan mode error untuk PDO agar kesalahan database terlihat jelas
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. Agregasi Data Mentah ke energy_data_hourly ---
    // Proses data yang sudah setidaknya 1 jam yang lalu untuk memastikan data lengkap
    echo "Aggregating raw data to hourly...\n";
    $stmt = $pdo->prepare("
        INSERT INTO energy_data_hourly (device_id, timestamp, total_energy_kwh, avg_power_w, min_power_w, max_power_w)
        SELECT
            ed.device_id,
            DATE_FORMAT(ed.created_at, '%Y-%m-%d %H:00:00') AS hour_timestamp,
            (MAX(ed.energy) - MIN(ed.energy)) AS total_energy_kwh,
            AVG(ed.power) AS avg_power_w,
            MIN(ed.power) AS min_power_w,
            MAX(ed.power) AS max_power_w
        FROM energy_data ed
        WHERE ed.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR) -- Hanya proses data yang sudah selesai 1 jam lalu
        GROUP BY ed.device_id, hour_timestamp
        HAVING (MAX(ed.energy) - MIN(ed.energy)) >= 0 -- Pastikan nilai energy tidak negatif
        ON DUPLICATE KEY UPDATE -- Jika sudah ada, update saja
            total_energy_kwh = VALUES(total_energy_kwh),
            avg_power_w = VALUES(avg_power_w),
            min_power_w = VALUES(min_power_w),
            max_power_w = VALUES(max_power_w);
    ");
    $stmt->execute();
    echo "Aggregated " . $stmt->rowCount() . " hourly records.\n";

    // --- 2. Agregasi Data Per Jam ke energy_data_daily ---
    // Proses data per jam yang sudah setidaknya 1 hari yang lalu
    echo "Aggregating hourly data to daily...\n";
    $stmt = $pdo->prepare("
        INSERT INTO energy_data_daily (device_id, date, total_energy_kwh, avg_power_w, min_power_w, max_power_w)
        SELECT
            eh.device_id,
            DATE(eh.timestamp) AS day_date,
            SUM(eh.total_energy_kwh) AS total_energy_kwh,
            AVG(eh.avg_power_w) AS avg_power_w,
            MIN(eh.min_power_w) AS min_power_w,
            MAX(eh.max_power_w) AS max_power_w
        FROM energy_data_hourly eh
        WHERE eh.timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY) -- Hanya proses data yang sudah selesai 1 hari lalu
        GROUP BY eh.device_id, day_date
        HAVING SUM(eh.total_energy_kwh) >= 0 -- Pastikan nilai energy tidak negatif
        ON DUPLICATE KEY UPDATE -- Jika sudah ada, update saja
            total_energy_kwh = VALUES(total_energy_kwh),
            avg_power_w = VALUES(avg_power_w),
            min_power_w = VALUES(min_power_w),
            max_power_w = VALUES(max_power_w);
    ");
    $stmt->execute();
    echo "Aggregated " . $stmt->rowCount() . " daily records.\n";

    // --- 3. Pembersihan Data Mentah (energy_data) ---
    // Pertahankan 7 hari terakhir
    echo "Cleaning up raw energy_data (older than 7 days)...\n";
    $stmt = $pdo->prepare("
        DELETE FROM energy_data
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    ");
    $stmt->execute();
    echo "Deleted " . $stmt->rowCount() . " raw energy_data records.\n";

    // --- 4. Pembersihan Data Agregasi Per Jam (energy_data_hourly) ---
    // Pertahankan 3 bulan terakhir
    echo "Cleaning up hourly energy_data (older than 3 months)....\n";
    $stmt = $pdo->prepare("
        DELETE FROM energy_data_hourly
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    ");
    $stmt->execute();
    echo "Deleted " . $stmt->rowCount() . " hourly energy_data records.\n";

    echo "Cleaning up daily energy_data (older than 5 years)...\n";
    $stmt = $pdo->prepare("
        DELETE FROM energy_data_daily
        WHERE date < DATE_SUB(NOW(), INTERVAL 5 YEAR);
    ");
    $stmt->execute();
    echo "Deleted " . $stmt->rowCount() . " daily energy_data records.\n";

    echo "Data maintenance script finished successfully.\n";

} catch (PDOException $e) {
    error_log("Data maintenance script error: " . $e->getMessage());
    echo "An error occurred: " . $e->getMessage() . "\n";
}
?>
