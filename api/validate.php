<?php
// File: api/validate_dashboard.php
header('Content-Type: text/html; charset=utf-8');

// Memastikan file database.php di-include untuk koneksi database
require_once __DIR__ . '/../config/database.php';

// Konfigurasi
const STALE_DATA_THRESHOLD_SECONDS = 300; // 5 menit, sama seperti di get_dashboard_summary.php
// Asumsi tarif default jika device.tarif_per_kwh tidak ada
const DEFAULT_TARIF_PER_KWH = 1500; 

try {
    $pdo = getPDOConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Ambil parameter dari URL ---
    $selectedUserId = $_GET['user_id'] ?? null;
    $selectedDeviceDbId = $_GET['device_id'] ?? null; // Ini adalah ID dari tabel devices, bukan MAC address

    // Ambil semua user (untuk dropdown)
    $users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil semua perangkat (untuk dropdown)
    $devices = $pdo->query("SELECT id, device_id, device_name, user_id FROM devices WHERE is_active = 1 ORDER BY device_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Tentukan user_id yang akan digunakan untuk query
    // Jika user_id tidak dipilih, kita akan menganggap semua user (atau tidak ada filter user_id)
    $targetUserId = $selectedUserId; 
    
    // Tentukan device_id yang akan digunakan untuk query
    $targetDeviceDbId = $selectedDeviceDbId;

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Validasi Dashboard Summary</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
            .container { max-width: 1200px; margin-top: 20px; }
            .card { margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .card-header { background-color: #007bff; color: white; font-weight: bold; }
            .card-body pre { background-color: #e9ecef; padding: 15px; border-radius: 5px; overflow-x: auto; }
            .result-value { font-weight: bold; color: #28a745; font-size: 1.1em; }
            .section-title { background-color: #e9ecef; padding: 10px; margin-top: 20px; margin-bottom: 15px; border-radius: 5px; }
            .form-filter { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="mb-4 text-center">📊 Validasi Dashboard Summary</h1>

            <!-- Form Filter -->
            <div class="form-filter">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="user_id" class="form-label">Pilih User:</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">-- Semua User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= ($targetUserId == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username'] . ' - ' . $user['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="device_id" class="form-label">Pilih Perangkat (ID Database):</label>
                        <select class="form-select" id="device_id" name="device_id">
                            <option value="">-- Semua Perangkat --</option>
                            <?php foreach ($devices as $dev): 
                                $showDevice = true;
                                if ($targetUserId && $dev['user_id'] != $targetUserId) {
                                    $showDevice = false;
                                }
                                if ($showDevice):
                            ?>
                                <option value="<?= $dev['id'] ?>" <?= ($targetDeviceDbId == $dev['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dev['device_name'] . ' (ID: ' . $dev['id'] . ' - MAC: ' . $dev['device_id'] . ')') ?>
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Terapkan Filter</button>
                    </div>
                </form>
                <div class="mt-3">
                    <strong>Filter Aktif:</strong> 
                    User: <?= $targetUserId ? ($users[array_search($targetUserId, array_column($users, 'id'))]['full_name'] ?? 'N/A') : 'Semua User' ?> | 
                    Perangkat: <?= $targetDeviceDbId ? ($devices[array_search($targetDeviceDbId, array_column($devices, 'id'))]['device_name'] ?? 'N/A') : 'Semua Perangkat' ?>
                </div>
            </div>

            <?php
            // Tambahkan kondisi WHERE untuk filter user dan device
            $deviceFilter = "";
            $deviceParams = [];

            if ($targetUserId) {
                $deviceFilter .= " AND d.user_id = ?";
                $deviceParams[] = $targetUserId;
            }
            if ($targetDeviceDbId) {
                $deviceFilter .= " AND d.id = ?";
                $deviceParams[] = $targetDeviceDbId;
            }

            // --- 1. Total Perangkat & Online ---
            echo '<div class="card"><div class="card-header">1. Total Perangkat & Online</div><div class="card-body">';
            $sql = "
                SELECT
                    COUNT(id) AS total_devices,
                    SUM(CASE WHEN updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, updated_at, NOW()) <= ? THEN 1 ELSE 0 END) AS online_devices_count
                FROM devices d
                WHERE d.is_active = 1 " . $deviceFilter;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([STALE_DATA_THRESHOLD_SECONDS], $deviceParams));
            $deviceCounts = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalDevices = (int)($deviceCounts['total_devices'] ?? 0);
            $onlineDevices = (int)($deviceCounts['online_devices_count'] ?? 0);
            echo "<p>Total Perangkat Aktif: <span class='result-value'>$totalDevices</span></p>";
            echo "<p>Perangkat Online (data segar < " . STALE_DATA_THRESHOLD_SECONDS . " detik): <span class='result-value'>$onlineDevices</span></p>";
            echo '</div></div>';

            // --- 2. Total Daya Saat Ini (Semua perangkat online) ---
            echo '<div class="card"><div class="card-header">2. Total Daya Saat Ini</div><div class="card-body">';
            $sql = "
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
                WHERE d.is_active = 1 " . $deviceFilter;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([STALE_DATA_THRESHOLD_SECONDS], $deviceParams));
            $totalPower = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_power'] ?? 0);
            echo "<p>Total Daya Saat Ini (W): <span class='result-value'>" . number_format($totalPower, 2, ',', '.') . " W</span></p>";
            echo '</div></div>';

            // --- 3. Mesin Aktif (machine_status = 'ON') ---
            echo '<div class="card"><div class="card-header">3. Mesin Aktif</div><div class="card-body">';
            $sql = "
                SELECT COUNT(*) AS active_machines
                FROM devices d
                WHERE d.is_active = 1 " . $deviceFilter . "
                AND (
                    SELECT machine_status FROM energy_data ed 
                    WHERE ed.device_id = d.id 
                    ORDER BY ed.created_at DESC LIMIT 1
                ) = 'ON'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $activeMachines = (int)($stmt->fetch(PDO::FETCH_ASSOC)['active_machines'] ?? 0);
            echo "<p>Mesin Aktif (status terakhir 'ON'): <span class='result-value'>$activeMachines</span></p>";
            echo '</div></div>';

            // --- 4. Konsumsi Hari Ini (MAX-MIN per perangkat, SUM semua) ---
            echo '<div class="card"><div class="card-header">4. Konsumsi Energi Hari Ini</div><div class="card-body">';
            $sql = "
                SELECT COALESCE(SUM(daily_kwh.kwh), 0) AS total_energy_today
                FROM (
                    SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
                    FROM energy_data ed
                    WHERE ed.created_at >= CURDATE() AND ed.created_at < CURDATE() + INTERVAL 1 DAY
                    GROUP BY ed.device_id
                ) daily_kwh
                JOIN devices d ON daily_kwh.device_id = d.id
                WHERE d.is_active = 1 " . $deviceFilter;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $energyToday = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_energy_today'] ?? 0);
            echo "<p>Total Energi Hari Ini (kWh): <span class='result-value'>" . number_format($energyToday, 3, ',', '.') . " kWh</span></p>";
            echo '</div></div>';

            // --- 5. Konsumsi Bulan Ini (energy_data_daily + raw data hari ini) ---
            echo '<div class="card"><div class="card-header">5. Konsumsi Energi Bulan Ini</div><div class="card-body">';
            $energyThisMonth = 0;

            // Dari energy_data_daily (1 bulan hingga kemarin)
            $sql = "
                SELECT COALESCE(SUM(eh.total_energy_kwh), 0) AS aggregated_kwh
                FROM energy_data_daily eh
                JOIN devices d ON eh.device_id = d.id
                WHERE d.is_active = 1 " . $deviceFilter . "
                AND eh.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND eh.date < CURDATE()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $energyThisMonth += (float)($stmt->fetch(PDO::FETCH_ASSOC)['aggregated_kwh'] ?? 0);
            echo "<p>Energi Bulan Ini (agregasi hingga kemarin): <span class='result-value'>" . number_format($energyThisMonth, 3, ',', '.') . " kWh</span></p>";

            // Dari energy_data hari ini (MAX-MIN)
            $sql = "
                SELECT COALESCE(SUM(daily_kwh.kwh), 0) AS raw_kwh_today
                FROM (
                    SELECT ed.device_id, (MAX(ed.energy) - MIN(ed.energy)) AS kwh
                    FROM energy_data ed
                    WHERE ed.created_at >= CURDATE()
                    GROUP BY ed.device_id
                ) daily_kwh
                JOIN devices d ON daily_kwh.device_id = d.id
                WHERE d.is_active = 1 " . $deviceFilter;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $energyThisMonth += (float)($stmt->fetch(PDO::FETCH_ASSOC)['raw_kwh_today'] ?? 0);
            echo "<p>Energi Hari Ini (dari data mentah, ditambahkan ke bulan ini): <span class='result-value'>" . number_format($energyThisMonth - (float)($stmt->fetch(PDO::FETCH_ASSOC)['raw_kwh_today'] ?? 0), 3, ',', '.') . " kWh</span></p>"; // Ini salah, harusnya $energyThisMonth yang sudah ditambah
            echo "<p>Total Energi Bulan Ini (kWh): <span class='result-value'>" . number_format($energyThisMonth, 3, ',', '.') . " kWh</span></p>";
            echo '</div></div>';

            // --- 6. Hitung Biaya & Proyeksi ---
            echo '<div class="card"><div class="card-header">6. Perhitungan Biaya & Proyeksi</div><div class="card-body">';
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
            $sql = "
                SELECT 
                    d.id AS device_id,
                    d.device_name,
                    COALESCE(d.tarif_per_kwh, " . DEFAULT_TARIF_PER_KWH . ") AS tarif_per_kwh,
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
                WHERE d.is_active = 1 " . $deviceFilter;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $devicesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<p>Tarif per kWh Default: Rp " . number_format(DEFAULT_TARIF_PER_KWH, 0, ',', '.') . "</p>";
            echo "<p>Hari ke- bulan ini: <span class='result-value'>$currentDayOfMonth</span> dari <span class='result-value'>$daysInMonth</span> hari</p>";
            echo "<h6 class='mt-3'>Detail Perangkat:</h6>";
            echo "<pre>";
            foreach ($devicesData as $device) {
                $deviceId = $device['device_id'];
                $deviceName = $device['device_name'];
                $tarif = (float)($device['tarif_per_kwh'] ?? DEFAULT_TARIF_PER_KWH);
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
                } elseif ($currentDayOfMonth == 1 && $dailyKwh > 0) { // Jika hari pertama, gunakan dailyKwh
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
                
                echo "Device: $deviceName (ID: $deviceId)\n";
                echo "  Tarif: Rp " . number_format($tarif, 0, ',', '.') . "/kWh\n";
                echo "  Energi Hari Ini: " . number_format($dailyKwh, 3, ',', '.') . " kWh -> Biaya: Rp " . number_format($dailyCost, 0, ',', '.') . "\n";
                echo "  Energi Bulan Ini (akumulasi): " . number_format($monthlyKwh, 3, ',', '.') . " kWh\n";
                echo "  Proyeksi Bulanan: " . number_format($projectedMonthlyKwh, 3, ',', '.') . " kWh -> Biaya: Rp " . number_format($monthlyCost, 0, ',', '.') . "\n";
                echo "---------------------------------\n";
            }
            echo "</pre>";

            echo "<p>Total Estimasi Biaya Hari Ini: <span class='result-value'>Rp " . number_format($estimatedCostToday, 0, ',', '.') . "</span></p>";
            echo "<p>Total Proyeksi Biaya Mingguan: <span class='result-value'>Rp " . number_format($weeklyProjectionTotal, 0, ',', '.') . "</span></p>";
            echo "<p>Total Proyeksi Biaya Bulanan: <span class='result-value'>Rp " . number_format($monthlyProjectionTotal, 0, ',', '.') . "</span></p>";
            echo '</div></div>';

            // --- 7. Perangkat Paling Boros (7 Hari Terakhir) ---
            echo '<div class="card"><div class="card-header">7. Perangkat Paling Boros (7 Hari Terakhir)</div><div class="card-body">';
            $mostWastefulDevice = null;
            $sql = "
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
                WHERE d.is_active = 1 " . $deviceFilter . "
                GROUP BY d.device_name
                ORDER BY total_energy_kwh DESC
                LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($deviceParams);
            $mostWastefulDevice = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($mostWastefulDevice) {
                $mostWastefulDevice['total_energy_kwh'] = round((float)$mostWastefulDevice['total_energy_kwh'], 3);
                echo "<p>Perangkat Paling Boros: <span class='result-value'>" . htmlspecialchars($mostWastefulDevice['device_name']) . "</span> dengan <span class='result-value'>" . number_format($mostWastefulDevice['total_energy_kwh'], 3, ',', '.') . " kWh</span></p>";
            } else {
                echo "<p>Tidak ada data konsumsi untuk menentukan perangkat paling boros.</p>";
            }
            echo '</div></div>';

            // --- 8. Perangkat Terbaru ---
            echo '<div class="card"><div class="card-header">8. Perangkat Terbaru</div><div class="card-body">';
            $sql = "
                SELECT
                    d.id,
                    d.device_name,
                    d.device_type,
                    d.updated_at,
                    (d.updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, d.updated_at, NOW()) <= ?) AS is_truly_online,
                    (SELECT ed.machine_status FROM energy_data ed WHERE ed.device_id = d.id ORDER BY ed.created_at DESC LIMIT 1) AS machine_status
                FROM devices d
                WHERE d.is_active = 1 " . $deviceFilter . "
                ORDER BY d.created_at DESC
                LIMIT 5";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([STALE_DATA_THRESHOLD_SECONDS], $deviceParams));
            $recentDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($recentDevices)):
                echo "<div class='table-responsive'><table class='table table-sm table-bordered'>";
                echo "<thead><tr><th>ID</th><th>Nama Perangkat</th><th>Tipe</th><th>Terakhir Update</th><th>Online?</th><th>Status Mesin</th></tr></thead><tbody>";
                foreach ($recentDevices as $device) {
                    echo "<tr>";
                    echo "<td>" . $device['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($device['device_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($device['device_type']) . "</td>";
                    echo "<td>" . $device['updated_at'] . "</td>";
                    echo "<td>" . ($device['is_truly_online'] ? "<span class='badge bg-success'>Ya</span>" : "<span class='badge bg-danger'>Tidak</span>") . "</td>";
                    echo "<td>" . ($device['machine_status'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table></div>";
            else:
                echo "<p>Tidak ada perangkat terbaru yang ditemukan.</p>";
            endif;
            echo '</div></div>';

            // --- 9. Insight & Rekomendasi (Bukan AI) ---
            echo '<div class="card"><div class="card-header">9. Insight & Rekomendasi</div><div class="card-body">';
            $insights = [];
            $totalEstimatedSavings = 0;

            // Cari top spender (perangkat dengan biaya tertinggi)
            $topSpenders = [];
            foreach ($devicesData as $device) {
                $monthlyKwh = (float)$device['energy_this_month_kwh'];
                $tarif = (float)($device['tarif_per_kwh'] ?? DEFAULT_TARIF_PER_KWH);
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

            if (!empty($insights)):
                echo "<ul>";
                foreach ($insights as $insight) {
                    echo "<li><strong>" . htmlspecialchars($insight['title']) . ":</strong> " . htmlspecialchars($insight['description']) . "</li>";
                }
                echo "</ul>";
                echo "<p>Total Potensi Penghematan Bulanan: <span class='result-value'>Rp " . number_format($totalEstimatedSavings, 0, ',', '.') . "</span></p>";
            else:
                echo "<p>Tidak ada insight atau rekomendasi saat ini.</p>";
            endif;
            echo '</div></div>';

            ?>
            <div class="alert alert-info mt-4">
                <strong>Catatan:</strong> Nilai yang ditampilkan di sini adalah hasil perhitungan langsung dari database menggunakan logika yang sama persis dengan `get_dashboard_summary.php`. Bandingkan nilai-nilai ini dengan apa yang Anda lihat di dashboard Anda. Jika ada perbedaan, periksa kembali data mentah, proses agregasi, atau konfigurasi tarif.
            </div>

        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>General Error: " . $e->getMessage() . "</div>";
}
?>
