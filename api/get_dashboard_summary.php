<?php
// api/get_dashboard_summary.php - REVISED
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}
$userId = $_SESSION['user_id'];
$response_data = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

try {
    $pdo = getPDOConnection();
    $STALE_DATA_THRESHOLD_SECONDS = 300; // 5 menit

    // --- 1. Total Perangkat & Online ---
    $stmt = $pdo->prepare("
        SELECT
            COUNT(id) AS total_devices,
            SUM(CASE WHEN updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, updated_at, NOW()) <= ? THEN 1 ELSE 0 END) AS online_devices_count
        FROM devices
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$STALE_DATA_THRESHOLD_SECONDS, $userId]);
    $deviceCounts = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDevices = (int)($deviceCounts['total_devices'] ?? 0);
    $onlineDevices = (int)($deviceCounts['online_devices_count'] ?? 0);

    // --- 2. Total Daya Saat Ini (Semua perangkat online) ---
    // Ambil daya terbaru dari setiap perangkat yang datanya masih segar (< 5 menit)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(latest.power), 0) AS total_power
        FROM devices d
        LEFT JOIN (
            SELECT ed1.device_id, ed1.power
            FROM energy_data ed1
            INNER JOIN (
                SELECT device_id, MAX(created_at) AS max_created_at
                FROM energy_data
                WHERE created_at >= NOW() - INTERVAL ? SECOND
                GROUP BY device_id
            ) ed2 ON ed1.device_id = ed2.device_id AND ed1.created_at = ed2.max_created_at
        ) latest ON d.id = latest.device_id
        WHERE d.user_id = ? AND d.is_active = 1
    ");
    $stmt->execute([$STALE_DATA_THRESHOLD_SECONDS, $userId]);
    $totalPower = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_power'] ?? 0);

    // --- 3. Mesin Aktif (machine_status = 'ON' DAN updated_at masih baru) ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS active_machines
        FROM devices d
        WHERE d.user_id = ? AND d.is_active = 1
        AND (d.updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, d.updated_at, NOW()) <= ?) -- Tambahkan kondisi ini
        AND (
            SELECT machine_status FROM energy_data ed 
            WHERE ed.device_id = d.id 
            ORDER BY ed.created_at DESC LIMIT 1
        ) = 'ON'
    ");
    $stmt->execute([$userId, $STALE_DATA_THRESHOLD_SECONDS]); 
    $activeMachines = (int)($stmt->fetch(PDO::FETCH_ASSOC)['active_machines'] ?? 0);

    // --- 4. Konsumsi Hari Ini (MAX-MIN per perangkat, SUM semua) ---
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(daily_kwh.kwh), 0) AS total_energy_today
        FROM (
            SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
            FROM energy_data ed
            WHERE ed.created_at >= CURDATE() AND ed.created_at < CURDATE() + INTERVAL 1 DAY
            GROUP BY ed.device_id
        ) daily_kwh
        JOIN devices d ON daily_kwh.device_id = d.id
        WHERE d.user_id = ? AND d.is_active = 1
    ");
    $stmt->execute([$userId]);
    $energyToday = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_energy_today'] ?? 0);

    // --- 5. Konsumsi Bulan Ini (energy_data_daily + raw data hari ini) ---
    $energyThisMonth = 0;

    // Dari energy_data_daily (1 bulan hingga kemarin)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(eh.total_energy_kwh), 0) AS aggregated_kwh
        FROM energy_data_daily eh
        JOIN devices d ON eh.device_id = d.id
        WHERE d.user_id = ? AND d.is_active = 1
        AND eh.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        AND eh.date < CURDATE()
    ");
    $stmt->execute([$userId]);
    $energyThisMonth += (float)($stmt->fetch(PDO::FETCH_ASSOC)['aggregated_kwh'] ?? 0);

    // Dari energy_data hari ini (MAX-MIN)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(daily_kwh.kwh), 0) AS raw_kwh_today
        FROM (
            SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
            FROM energy_data ed
            WHERE ed.created_at >= CURDATE()
            GROUP BY ed.device_id
        ) daily_kwh
        JOIN devices d ON daily_kwh.device_id = d.id
        WHERE d.user_id = ? AND d.is_active = 1
    ");
    $stmt->execute([$userId]);
    $energyThisMonth += (float)($stmt->fetch(PDO::FETCH_ASSOC)['raw_kwh_today'] ?? 0);

    // --- 6. Hitung Biaya & Proyeksi ---
    $estimatedCostToday = 0;
    $weeklyProjectionTotal = 0;
    $monthlyProjectionTotal = 0;
    $deviceCostsBreakdown = [
        'daily' => ['total' => 0, 'breakdown' => []],
        'projections' => [
            'weekly' => ['total' => 0, 'breakdown' => []],
            'monthly' => ['total' => 0, 'breakdown' => []],
        ]
    ];

    $currentDayOfMonth = (int)date('d');
    $daysInMonth = (int)date('t');

    // Ambil data per perangkat: tarif, konsumsi hari ini, konsumsi bulan ini
    $stmt = $pdo->prepare("
        SELECT 
            d.id AS device_id,
            d.device_name,
            d.tarif_per_kwh,
            COALESCE(daily_kwh.kwh, 0) AS energy_today_kwh,
            COALESCE(monthly_kwh.kwh, 0) AS energy_this_month_kwh
        FROM devices d
        LEFT JOIN (
            SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
            FROM energy_data ed
            WHERE ed.created_at >= CURDATE() AND ed.created_at < CURDATE() + INTERVAL 1 DAY
            GROUP BY ed.device_id
        ) daily_kwh ON d.id = daily_kwh.device_id
        LEFT JOIN (
            SELECT 
                combined.device_id,
                SUM(combined.kwh) AS kwh
            FROM (
                SELECT eh.device_id, eh.total_energy_kwh AS kwh
                FROM energy_data_daily eh
                WHERE eh.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND eh.date < CURDATE()
                UNION ALL
                SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
                FROM energy_data ed
                WHERE ed.created_at >= CURDATE()
                GROUP BY ed.device_id
            ) combined
            GROUP BY combined.device_id
        ) monthly_kwh ON d.id = monthly_kwh.device_id
        WHERE d.user_id = ? AND d.is_active = 1
    ");
    $stmt->execute([$userId]);
    $devicesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($devicesData as $device) {
        $deviceId = $device['device_id'];
        $deviceName = $device['device_name'];
        $tarif = (float)($device['tarif_per_kwh'] ?? 1500);
        $dailyKwh = (float)$device['energy_today_kwh'];
        $monthlyKwh = (float)$device['energy_this_month_kwh'];

        // Biaya Harian
        $dailyCost = $dailyKwh * $tarif;
        $estimatedCostToday += $dailyCost;
        $deviceCostsBreakdown['daily']['total'] += $dailyCost;
        $deviceCostsBreakdown['daily']['breakdown'][$deviceId] = [
            'name' => $deviceName,
            'kwh' => round($dailyKwh, 3),
            'cost' => round($dailyCost, 0)
        ];

        // Proyeksi Mingguan = Biaya Hari Ini × 7
        $weeklyCost = $dailyCost * 7;
        $weeklyProjectionTotal += $weeklyCost;
        $deviceCostsBreakdown['projections']['weekly']['total'] += $weeklyCost;
        $deviceCostsBreakdown['projections']['weekly']['breakdown'][$deviceId] = [
            'name' => $deviceName,
            'kwh' => round($dailyKwh * 7, 3),
            'cost' => round($weeklyCost, 0)
        ];

        // Proyeksi Bulanan = (Konsumsi Bulan Ini / Hari Ke-) × Jumlah Hari × Tarif
        $projectedMonthlyKwh = 0;
        if ($currentDayOfMonth > 0 && $monthlyKwh > 0) {
            $avgDailyKwh = $monthlyKwh / $currentDayOfMonth;
            $projectedMonthlyKwh = $avgDailyKwh * $daysInMonth;
        } elseif ($currentDayOfMonth == 1 && $dailyKwh > 0) {
            $projectedMonthlyKwh = $dailyKwh * $daysInMonth;
        }

        $monthlyCost = $projectedMonthlyKwh * $tarif;
        $monthlyProjectionTotal += $monthlyCost;
        $deviceCostsBreakdown['projections']['monthly']['total'] += $monthlyCost;
        $deviceCostsBreakdown['projections']['monthly']['breakdown'][$deviceId] = [
            'name' => $deviceName,
            'kwh' => round($projectedMonthlyKwh, 3),
            'cost' => round($monthlyCost, 0)
        ];
    }

    // --- 7. Perangkat Paling Boros (7 Hari Terakhir) ---
    $mostWastefulDevice = null;
    $stmt = $pdo->prepare("
        SELECT d.device_name, COALESCE(SUM(combined.kwh), 0) AS total_energy_kwh
        FROM (
            SELECT eh.device_id, eh.total_energy_kwh AS kwh
            FROM energy_data_daily eh
            WHERE eh.date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND eh.date < CURDATE()
            UNION ALL
            SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
            FROM energy_data ed
            WHERE ed.created_at >= CURDATE()
            GROUP BY ed.device_id
        ) combined
        JOIN devices d ON combined.device_id = d.id
        WHERE d.user_id = ? AND d.is_active = 1
        GROUP BY d.device_name
        ORDER BY total_energy_kwh DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $mostWastefulDevice = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mostWastefulDevice) {
        $mostWastefulDevice['total_energy_kwh'] = round((float)$mostWastefulDevice['total_energy_kwh'], 3);
    }

    // --- 8. Perangkat Terbaru ---
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.device_name,
            d.device_type,
            d.updated_at,
            (d.updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, d.updated_at, NOW()) <= ?) AS is_truly_online,
            (SELECT ed.machine_status FROM energy_data ed WHERE ed.device_id = d.id ORDER BY ed.created_at DESC LIMIT 1) AS machine_status
        FROM devices d
        WHERE d.user_id = ? AND d.is_active = 1
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$STALE_DATA_THRESHOLD_SECONDS, $userId]);
    $recentDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 9. Insight & Rekomendasi Berbasis Data (Bukan AI) ---
    $insights = [];
    $totalEstimatedSavings = 0;

    // Cari top spender (perangkat dengan biaya tertinggi)
    $topSpenders = [];
    foreach ($devicesData as $device) {
        $monthlyKwh = (float)$device['energy_this_month_kwh'];
        $tarif = (float)($device['tarif_per_kwh'] ?? 1500);
        $monthlyCost = $monthlyKwh * $tarif;
        $topSpenders[] = [
            'device_id' => $device['device_id'],
            'device_name' => $device['device_name'],
            'monthly_kwh' => $monthlyKwh,
            'monthly_cost' => $monthlyCost
        ];
    }
    usort($topSpenders, function($a, $b) { return $b['monthly_cost'] <=> $a['monthly_cost']; });

    if (!empty($topSpenders) && $topSpenders[0]['monthly_cost'] > 5000) {
        $spender = $topSpenders[0];
        $estimatedSavings = $spender['monthly_cost'] * 0.1; // Asumsi hemat 10%
        $insights[] = [
            'type' => 'top_spender',
            'icon' => 'fas fa-bolt',
            'title' => 'Perangkat dengan Konsumsi Tertinggi',
            'description' => "{$spender['device_name']} menghabiskan Rp " . number_format($spender['monthly_cost'], 0, ',', '.') . " bulan ini. Mengurangi pemakaian 10% bisa menghemat Rp " . number_format($estimatedSavings, 0, ',', '.') . "/bulan.",
            'estimated_savings_rp' => $estimatedSavings
        ];
        $totalEstimatedSavings += $estimatedSavings;
    }

    // Cek potensi penghematan umum
    if ($totalEstimatedSavings < 5000 && $monthlyProjectionTotal > 0) {
        $generalSavings = $monthlyProjectionTotal * 0.05;
        $insights[] = [
            'type' => 'general_habit',
            'icon' => 'fas fa-leaf',
            'title' => 'Kebiasaan Hemat Energi',
            'description' => "Dengan mematikan perangkat yang tidak digunakan dan mencabut charger, Anda bisa menghemat hingga Rp " . number_format($generalSavings, 0, ',', '.') . "/bulan.",
            'estimated_savings_rp' => $generalSavings
        ];
        $totalEstimatedSavings += $generalSavings;
    }

    // --- Susun Respons ---
    $response_data = [
        'status' => 'success',
        'total_devices' => $totalDevices,
        'online_devices_count' => $onlineDevices,
        'active_machines_count' => $activeMachines,
        'total_power' => round($totalPower, 2),
        'total_energy_today' => round($energyToday, 3),
        'total_energy_this_month' => round($energyThisMonth, 3),
        'estimated_cost_today' => round($estimatedCostToday, 0),
        'device_costs' => [
            'daily' => [
                'total' => round($deviceCostsBreakdown['daily']['total'], 0),
                'breakdown' => $deviceCostsBreakdown['daily']['breakdown']
            ],
            'projections' => [
                'weekly' => [
                    'total' => round($weeklyProjectionTotal, 0),
                    'breakdown' => $deviceCostsBreakdown['projections']['weekly']['breakdown']
                ],
                'monthly' => [
                    'total' => round($monthlyProjectionTotal, 0),
                    'breakdown' => $deviceCostsBreakdown['projections']['monthly']['breakdown']
                ]
            ]
        ],
        'most_wasteful_device' => $mostWastefulDevice,
        'recent_devices' => $recentDevices,
        'insights_recommendations' => [
            'overall_savings_potential' => [
                'min_rp' => floor($totalEstimatedSavings * 0.5),
                'max_rp' => floor($totalEstimatedSavings),
                'min_percent' => $monthlyProjectionTotal > 0 ? round(($totalEstimatedSavings * 0.5 / $monthlyProjectionTotal) * 100) : 0,
                'max_percent' => $monthlyProjectionTotal > 0 ? round(($totalEstimatedSavings / $monthlyProjectionTotal) * 100) : 0,
                'current_monthly_bill_rp' => floor($monthlyProjectionTotal)
            ],
            'recommendations' => $insights
        ]
    ];

} catch (PDOException $e) {
    error_log("Database error in get_dashboard_summary.php: " . $e->getMessage());
    $response_data = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
} catch (Exception $e) {
    error_log("General error in get_dashboard_summary.php: " . $e->getMessage());
    $response_data = ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
}

echo json_encode($response_data);
exit();
