<?php
// File: api/export_analytics.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="analisis_energi_' . date('Ymd') . '.csv"');

date_default_timezone_set('Asia/Jakarta');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    echo "Unauthorized";
    exit();
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$deviceId = $_GET['device_id'] ?? 'all';
$period = $_GET['period'] ?? '7days';

try {
    $pdo = getPDOConnection();

    // Tentukan rentang tanggal
    $dateRange = getDateRange($period);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];

    $deviceFilter = "";
    $params = [$userId, $startDate, $endDate];
    if ($deviceId !== 'all') {
        $deviceFilter = "AND d.id = ?";
        $params[] = $deviceId;
    }

    // Ambil data
    $stmt = $pdo->prepare("
        SELECT
            DATE(ed.created_at) AS tgl,
            d.device_name,
            d.device_id AS device_hardware_id,
            AVG(ed.power) AS avg_power,
            AVG(ed.voltage) AS avg_voltage,
            AVG(ed.current) AS avg_current,
            (MAX(ed.energy) - MIN(ed.energy)) AS energy,
            (MAX(ed.energy) - MIN(ed.energy)) * d.tarif_per_kwh AS cost,
            CASE WHEN AVG(ed.machine_status) = 'ON' THEN 'ON' ELSE 'OFF' END AS status
        FROM energy_data ed
        JOIN devices d ON ed.device_id = d.id
        WHERE d.user_id = ? AND d.is_active = 1
          AND ed.created_at >= ? AND ed.created_at < ?
          $deviceFilter
        GROUP BY DATE(ed.created_at), d.id, d.device_name, d.device_id, d.tarif_per_kwh
        ORDER BY tgl DESC, d.device_name ASC
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buat output CSV
    $output = fopen('php://output', 'w');

    // Header CSV
    fputcsv($output, [
        'Tanggal',
        'Nama Perangkat',
        'ID Hardware',
        'Daya Rata-rata (W)',
        'Tegangan Rata-rata (V)',
        'Arus Rata-rata (A)',
        'Energi (kWh)',
        'Biaya (Rp)',
        'Status'
    ], ';');

    // Data
    foreach ($results as $row) {
        fputcsv($output, [
            $row['tgl'],
            $row['device_name'],
            $row['device_hardware_id'],
            number_format((float)$row['avg_power'], 2, ',', '.'),
            number_format((float)$row['avg_voltage'], 2, ',', '.'),
            number_format((float)$row['avg_current'], 2, ',', '.'),
            number_format((float)$row['energy'], 3, ',', '.'),
            number_format((float)$row['cost'], 0, ',', '.'),
            $row['status']
        ], ';');
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("API Error (export_analytics): " . $e->getMessage());
    echo "Error: " . $e->getMessage();
    exit();
}

// Fungsi bantuan (sama dengan di get_analytics_data.php)
function getDateRange($period) {
    $now = new DateTime();
    $end = clone $now;
    $start = clone $now;

    switch ($period) {
        case '7days':
            $start->modify('-7 days');
            break;
        case '30days':
            $start->modify('-30 days');
            break;
        case '90days':
            $start->modify('-90 days');
            break;
        case 'this_month':
            $start->modify('first day of this month');
            break;
        case 'last_month':
            $start->modify('first day of last month');
            $end->modify('first day of this month');
            break;
        default:
            $start->modify('-7 days');
    }

    return [
        'start' => $start->format('Y-m-d 00:00:00'),
        'end' => $end->format('Y-m-d 23:59:59')
    ];
}
?>
