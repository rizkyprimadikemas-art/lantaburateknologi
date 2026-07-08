<?php
// File: api/get_analytics_data.php
header('Content-Type: application/json');

// Set zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/electricity_tariffs.php'; // Pastikan ini sudah ada dan berisi $electricityTariffs

$response = [
    'status' => 'error',
    'message' => 'Unauthorized'
];

// Memastikan pengguna sudah login
if (!isLoggedIn()) {
    error_log("Analytics API: Unauthorized access attempt.");
    echo json_encode($response);
    exit();
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Parameter dari request GET
$deviceId = $_GET['device_id'] ?? 'all'; // Bisa 'all' atau ID perangkat spesifik
$requestType = $_GET['type'] ?? 'summary'; // 'summary', 'energy_chart', 'device_distribution', 'daily_details'
$summaryPeriod = $_GET['period'] ?? '30days'; // Untuk summary: '7days', '30days', 'this_month', 'last_month'
$chartGranularity = $_GET['chart_granularity'] ?? 'daily'; // Untuk energy_chart: 'daily', 'weekly', 'monthly', 'hourly'

error_log("Analytics API Request: userId=$userId, deviceId=$deviceId, requestType=$requestType, summaryPeriod=$summaryPeriod, chartGranularity=$chartGranularity");

try {
    $pdo = getPDOConnection();

    // --- Helper Functions for Recommendations ---

    /**
     * Calculates the cost for a given kWh consumption.
     * @param float $kwh_consumption
     * @param array $tariffs
     * @param string $time_of_day 'day' or 'peak' for ToU tariffs
     * @return float Cost in Rupiah
     */
    function calculateCost($kwh_consumption, $tariffs, $time_of_day = 'day') {
        $rate = $tariffs['kwh_rate'];
        if ($tariffs['has_time_of_use_tariff'] && $time_of_day === 'peak') {
            $rate *= $tariffs['peak_hour_rate_multiplier'];
        }
        return $kwh_consumption * $rate;
    }

    /**
     * Generates insights and recommendations based on device data and tariffs.
     * @param int $userId
     * @param string $deviceId 'all' or specific device ID
     * @param PDO $pdo
     * @param array $tariffs
     * @return array
     */
    function generateInsightsAndRecommendations($userId, $deviceId, $pdo, $tariffs) {
        $recommendations = [];
        $totalEstimatedSavings = 0;
        $currentMonthlyBill = 0; // Will be calculated from actual usage

        // Fetch device data for the last month for all devices or a specific one
        // Adjusted to use 'energy_data' table and calculate energy_diff for daily consumption
        $whereDeviceFilter = "";
        if ($deviceId !== 'all') {
            $whereDeviceFilter = " AND d.id = :device_id";
        }

        $stmt = $pdo->prepare("
            SELECT
                d.id AS device_db_id,
                d.device_name,
                d.device_id,
                d.device_type,
                d.estimated_standby_power_w,
                d.efficiency_rating,
                d.purchase_date,
                SUM(daily_kwh.kwh_daily_consumption) AS total_kwh_last_month,
                SUM(CASE WHEN TIME(daily_kwh.day_timestamp) BETWEEN :peak_start_1 AND :peak_end_1 THEN daily_kwh.kwh_daily_consumption ELSE 0 END) AS peak_kwh_last_month,
                SUM(CASE WHEN TIME(daily_kwh.day_timestamp) NOT BETWEEN :peak_start_2 AND :peak_end_2 THEN daily_kwh.kwh_daily_consumption ELSE 0 END) AS off_peak_kwh_last_month,
                COUNT(DISTINCT DATE(daily_kwh.day_timestamp)) AS active_days_last_month
            FROM (
                SELECT
                    ed.device_id,
                    DATE(ed.created_at) AS day_timestamp,
                    (MAX(ed.energy) - MIN(ed.energy)) AS kwh_daily_consumption
                FROM energy_data ed
                GROUP BY ed.device_id, DATE(ed.created_at)
            ) daily_kwh
            JOIN devices d ON daily_kwh.device_id = d.id
            WHERE d.user_id = :user_id AND daily_kwh.day_timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            " . $whereDeviceFilter . "
            GROUP BY d.id
            ORDER BY total_kwh_last_month DESC
        ");
        
        // Bind parameters explicitly
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':peak_start_1', $tariffs['peak_hour_start']);
        $stmt->bindParam(':peak_end_1', $tariffs['peak_hour_end']);
        $stmt->bindParam(':peak_start_2', $tariffs['peak_hour_start']);
        $stmt->bindParam(':peak_end_2', $tariffs['peak_hour_end']);

        if ($deviceId !== 'all') {
            $stmt->bindParam(':device_id', $deviceId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $devicesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate current monthly bill from fetched data
        foreach ($devicesData as $device) {
            $currentMonthlyBill += calculateCost($device['off_peak_kwh_last_month'], $tariffs, 'day');
            if ($tariffs['has_time_of_use_tariff']) {
                $currentMonthlyBill += calculateCost($device['peak_kwh_last_month'], $tariffs, 'peak');
            } else {
                $currentMonthlyBill += calculateCost($device['peak_kwh_last_month'], $tariffs, 'day'); // If no ToU, just add with normal rate
            }
        }
        $currentMonthlyBill += $tariffs['fixed_monthly_charge'];

        $topSpenders = [];
        $vampireDrainDevices = [];
        $peakHourOffenders = [];
        $oldInefficientDevices = [];

        foreach ($devicesData as $device) {
            $deviceMonthlyCost = calculateCost($device['off_peak_kwh_last_month'], $tariffs, 'day');
            if ($tariffs['has_time_of_use_tariff']) {
                $deviceMonthlyCost += calculateCost($device['peak_kwh_last_month'], $tariffs, 'peak');
            } else {
                $deviceMonthlyCost += calculateCost($device['peak_kwh_last_month'], $tariffs, 'day');
            }

            // --- Top Spenders ---
            $topSpenders[] = [
                'device_db_id' => $device['device_db_id'],
                'device_name' => $device['device_name'],
                'total_kwh_last_month' => $device['total_kwh_last_month'],
                'monthly_cost' => $deviceMonthlyCost
            ];

            // --- Vampire Drain (Standby Power) ---
            $standbyPowerW = (float)$device['estimated_standby_power_w'];
            if ($standbyPowerW > 0.5) { // Threshold for significant standby power
                // Assume 20 hours/day standby (24 hours - 4 hours active use)
                $standbyKwhPerMonth = $standbyPowerW * 20 * 30 / 1000;
                $standbyCost = calculateCost($standbyKwhPerMonth, $tariffs, 'day');
                if ($standbyCost > 100) { // Only recommend if cost is significant
                    $vampireDrainDevices[] = [
                        'device_db_id' => $device['device_db_id'],
                        'device_name' => $device['device_name'],
                        'standby_power_w' => $standbyPowerW,
                        'standby_cost' => $standbyCost
                    ];
                }
            }

            // --- Peak Hour Optimization ---
            if ($tariffs['has_time_of_use_tariff'] && $device['peak_kwh_last_month'] > 0) {
                $peakKwhCost = calculateCost($device['peak_kwh_last_month'], $tariffs, 'peak');
                $normalKwhCost = calculateCost($device['peak_kwh_last_month'], $tariffs, 'day');
                $potentialSavingsFromOffPeak = $peakKwhCost - $normalKwhCost;

                if ($potentialSavingsFromOffPeak > 500) { // Significant savings
                    $peakHourOffenders[] = [
                        'device_db_id' => $device['device_db_id'],
                        'device_name' => $device['device_name'],
                        'peak_kwh_cost' => $peakKwhCost,
                        'potential_savings' => $potentialSavingsFromOffPeak
                    ];
                }
            }

            // --- Efficiency Upgrade ---
            if ($device['purchase_date']) {
                $purchaseDate = new DateTime($device['purchase_date']);
                $now = new DateTime();
                $age = $now->diff($purchaseDate)->y;

                $isMajorAppliance = in_array($device['device_type'], ['AC', 'Kulkas', 'Pemanas Air', 'Mesin Cuci']);
                $isInefficient = in_array($device['efficiency_rating'], ['Low', 'Medium']);

                if ($isMajorAppliance && $age >= 5 && $isInefficient && $deviceMonthlyCost > 10000) {
                    // Estimate savings if upgraded (e.g., 20-30% for old inefficient appliances)
                    $estimatedUpgradeSavings = $deviceMonthlyCost * 0.25;
                    if ($estimatedUpgradeSavings > 2000) {
                        $oldInefficientDevices[] = [
                            'device_db_id' => $device['device_db_id'],
                            'device_name' => $device['device_name'],
                            'device_type' => $device['device_type'],
                            'age_years' => $age,
                            'estimated_savings' => $estimatedUpgradeSavings
                        ];
                    }
                }
            }
        }

        // Sort top spenders and add recommendations
        usort($topSpenders, function($a, $b) { return $b['monthly_cost'] <=> $a['monthly_cost']; });
        foreach (array_slice($topSpenders, 0, 2) as $spender) { // Top 2 spenders
            if ($spender['monthly_cost'] > 5000) { // Only recommend if cost is significant
                // Assume 1 hour/day reduction saves (monthly cost / total active hours) * 30 days
                // Simplified: 10% reduction for 1 hour less
                $dailyKwh = $spender['total_kwh_last_month'] / 30;
                $savingsPerDay = calculateCost($dailyKwh * 0.1, $tariffs, 'day'); // Assume 10% reduction in daily KWH
                $estimatedSavings = $savingsPerDay * 30;

                if ($estimatedSavings > 500) {
                    $recommendations[] = [
                        'id' => 'rec_top_spender_' . $spender['device_db_id'],
                        'type' => 'top_spender',
                        'title' => 'Optimalkan ' . $spender['device_name'],
                        'description' => $spender['device_name'] . ' adalah penyumbang terbesar tagihan listrik Anda (Rp ' . number_format($spender['monthly_cost'], 0, ',', '.') . '/bulan). Mengurangi penggunaannya 1 jam/hari dapat menghemat Rp ' . number_format($estimatedSavings, 0, ',', '.') . '/bulan.',
                        'device_id' => $spender['device_db_id'],
                        'device_name' => $spender['device_name'],
                        'estimated_savings_rp' => $estimatedSavings,
                        'icon' => 'fas fa-bolt'
                    ];
                    $totalEstimatedSavings += $estimatedSavings;
                }
            }
        }

        // Add vampire drain recommendations
        usort($vampireDrainDevices, function($a, $b) { return $b['standby_cost'] <=> $a['standby_cost']; });
        foreach (array_slice($vampireDrainDevices, 0, 1) as $vampire) { // Top 1 vampire
            $recommendations[] = [
                'id' => 'rec_vampire_' . $vampire['device_db_id'],
                'type' => 'vampire_drain',
                'title' => 'Atasi Daya Siaga ' . $vampire['device_name'],
                'description' => $vampire['device_name'] . ' mengonsumsi daya siaga sekitar ' . $vampire['standby_power_w'] . 'W, setara Rp ' . number_format($vampire['standby_cost'], 0, ',', '.') . '/bulan. Cabut atau gunakan stop kontak pintar saat tidak digunakan.',
                'device_id' => $vampire['device_db_id'],
                'device_name' => $vampire['device_name'],
                'estimated_savings_rp' => $vampire['standby_cost'],
                'icon' => 'fas fa-plug'
            ];
            $totalEstimatedSavings += $vampire['standby_cost'];
        }

        // Add peak hour optimization recommendations
        usort($peakHourOffenders, function($a, $b) { return $b['potential_savings'] <=> $a['potential_savings']; });
        foreach (array_slice($peakHourOffenders, 0, 1) as $offender) { // Top 1 offender
            $recommendations[] = [
                'id' => 'rec_peak_hour_' . $offender['device_db_id'],
                'type' => 'peak_hour_optimization',
                'title' => 'Jadwalkan Ulang ' . $offender['device_name'],
                'description' => 'Penggunaan ' . $offender['device_name'] . ' sering terjadi di jam sibuk (' . $tariffs['peak_hour_start'] . '-' . $tariffs['peak_hour_end'] . '). Memindahkannya ke luar jam tersebut bisa menghemat Rp ' . number_format($offender['potential_savings'], 0, ',', '.') . '/bulan.',
                'device_id' => $offender['device_db_id'],
                'device_name' => $offender['device_name'],
                'estimated_savings_rp' => $offender['potential_savings'],
                'icon' => 'fas fa-clock'
            ];
            $totalEstimatedSavings += $offender['potential_savings'];
        }

        // Add efficiency upgrade recommendations
        usort($oldInefficientDevices, function($a, $b) { return $b['estimated_savings'] <=> $a['estimated_savings']; });
        foreach (array_slice($oldInefficientDevices, 0, 1) as $oldDevice) { // Top 1 old inefficient device
            $recommendations[] = [
                'id' => 'rec_upgrade_' . $oldDevice['device_db_id'],
                'type' => 'upgrade_efficiency',
                'title' => 'Pertimbangkan Upgrade ' . $oldDevice['device_name'],
                'description' => $oldDevice['device_name'] . ' (' . $oldDevice['device_type'] . ') Anda sudah berusia ' . $oldDevice['age_years'] . ' tahun dan mungkin tidak efisien. Upgrade ke model baru berpotensi menghemat Rp ' . number_format($oldDevice['estimated_savings'], 0, ',', '.') . '/bulan.',
                'device_id' => $oldDevice['device_db_id'],
                'device_name' => $oldDevice['device_name'],
                'estimated_savings_rp' => $oldDevice['estimated_savings'],
                'icon' => 'fas fa-lightbulb'
            ];
            $totalEstimatedSavings += $oldDevice['estimated_savings'];
        }

        // Add a general habit change recommendation if no specific ones are found or if total savings are low
        if (empty($recommendations) || $totalEstimatedSavings < 5000) {
            $recommendations[] = [
                'id' => 'rec_general_habit',
                'type' => 'habit_change',
                'title' => 'Tingkatkan Kebiasaan Hemat Energi',
                'description' => 'Selalu matikan mesin atau alat saat tidak digunakan. Kebiasaan kecil ini dapat memberikan dampak besar pada tagihan listrik Anda.',
                'device_id' => null,
                'device_name' => 'Umum',
                'estimated_savings_rp' => 0, // No specific savings for general advice
                'icon' => 'fas fa-leaf'
            ];
        }

        // Calculate overall savings potential
        $minSavingsRp = $totalEstimatedSavings * 0.5; // Assume 50% of total estimated is easily achievable
        $maxSavingsRp = $totalEstimatedSavings;

        $minSavingsPercent = ($currentMonthlyBill > 0) ? round(($minSavingsRp / $currentMonthlyBill) * 100) : 0;
        $maxSavingsPercent = ($currentMonthlyBill > 0) ? round(($maxSavingsRp / $currentMonthlyBill) * 100) : 0;

        // Ensure at least a small default saving if no specific recommendations
        if ($maxSavingsRp < 1000 && $currentMonthlyBill > 0) {
            $minSavingsRp = $currentMonthlyBill * 0.05; // 5% of current bill
            $maxSavingsRp = $currentMonthlyBill * 0.10; // 10% of current bill
            $minSavingsPercent = 5;
            $maxSavingsPercent = 10;
        }


        return [
            'overall_savings_potential' => [
                'min_rp' => floor($minSavingsRp),
                'max_rp' => floor($maxSavingsRp),
                'min_percent' => $minSavingsPercent,
                'max_percent' => $maxSavingsPercent,
                'current_monthly_bill_rp' => floor($currentMonthlyBill)
            ],
            'recommendations' => $recommendations
        ];
    }

    // Filter dasar untuk perangkat (jika deviceId bukan 'all')
    $deviceFilterSql = "";
    $deviceFilterParams = [];
    if ($deviceId !== 'all') {
        $deviceFilterSql = "AND d.id = ?";
        $deviceFilterParams[] = $deviceId;
    }

    switch ($requestType) {
        case 'summary':
            // Mendapatkan rentang tanggal untuk periode saat ini dan periode sebelumnya
            $currentPeriodRange = getSummaryPeriodRange($summaryPeriod);
            $currentStartDate = $currentPeriodRange['start'];
            $currentEndDate = $currentPeriodRange['end'];

            $previousPeriodRange = getPreviousSummaryPeriodRange($summaryPeriod);
            $previousStartDate = $previousPeriodRange['start'];
            $previousEndDate = $previousPeriodRange['end'];

            error_log("Summary Period: Current=[$currentStartDate to $currentEndDate], Previous=[$previousStartDate to $previousEndDate]");

            // --- Mengambil statistik periode saat ini ---
            $currentParams = array_merge([$currentStartDate, $currentEndDate, $userId], $deviceFilterParams);
            $sqlCurrentStats = "
                SELECT
                    COALESCE(AVG(ed.power), 0) AS avg_power,
                    COALESCE(SUM(ed.energy_diff), 0) AS total_energy,
                    COALESCE(SUM(ed.energy_diff * d.tarif_per_kwh), 0) AS total_cost
                FROM (
                    SELECT
                        device_id,
                        AVG(power) AS power,
                        (MAX(energy) - MIN(energy)) AS energy_diff,
                        created_at
                    FROM energy_data
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY device_id, DATE(created_at)
                ) ed
                JOIN devices d ON ed.device_id = d.id
                WHERE d.user_id = ? AND d.is_active = 1
                $deviceFilterSql
            ";
            error_log("Summary Current SQL: " . preg_replace('/\s+/', ' ', $sqlCurrentStats) . " Params: " . json_encode($currentParams));
            $stmtCurrentStats = $pdo->prepare($sqlCurrentStats);
            $stmtCurrentStats->execute($currentParams);
            $currentStats = $stmtCurrentStats->fetch(PDO::FETCH_ASSOC);
            if ($stmtCurrentStats->errorCode() !== '00000') {
                error_log("Summary Current SQL Error: " . json_encode($stmtCurrentStats->errorInfo()));
            }

            $avgPower = (float)$currentStats['avg_power'];
            $totalEnergy = (float)$currentStats['total_energy'];
            $totalCost = (float)$currentStats['total_cost'];

            // --- Mengambil statistik periode sebelumnya ---
            $prevParams = array_merge([$previousStartDate, $previousEndDate, $userId], $deviceFilterParams);
            $sqlPreviousStats = "
                SELECT
                    COALESCE(AVG(ed.power), 0) AS avg_power,
                    COALESCE(SUM(ed.energy_diff), 0) AS total_energy,
                    COALESCE(SUM(ed.energy_diff * d.tarif_per_kwh), 0) AS total_cost
                FROM (
                    SELECT
                        device_id,
                        AVG(power) AS power,
                        (MAX(energy) - MIN(energy)) AS energy_diff,
                        created_at
                    FROM energy_data
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY device_id, DATE(created_at)
                ) ed
                JOIN devices d ON ed.device_id = d.id
                WHERE d.user_id = ? AND d.is_active = 1
                $deviceFilterSql
            ";
            error_log("Summary Previous SQL: " . preg_replace('/\s+/', ' ', $sqlPreviousStats) . " Params: " . json_encode($prevParams));
            $stmtPreviousStats = $pdo->prepare($sqlPreviousStats);
            $stmtPreviousStats->execute($prevParams);
            $previousStats = $stmtPreviousStats->fetch(PDO::FETCH_ASSOC);
            if ($stmtPreviousStats->errorCode() !== '00000') {
                error_log("Summary Previous SQL Error: " . json_encode($stmtPreviousStats->errorInfo()));
            }

            $prevAvgPower = (float)$previousStats['avg_power'];
            $prevTotalEnergy = (float)$previousStats['total_energy'];
            $prevTotalCost = (float)$previousStats['total_cost'];

            // --- Menghitung Uptime untuk periode saat ini ---
            $uptimeParams = array_merge([$currentStartDate, $currentEndDate, $userId], $deviceFilterParams);
            $sqlUptime = "
                SELECT
                    COUNT(DISTINCT DATE(ed.created_at)) AS days_with_data
                FROM energy_data ed
                JOIN devices d ON ed.device_id = d.id
                WHERE ed.created_at >= ? AND ed.created_at <= ? AND d.user_id = ? AND d.is_active = 1
                  $deviceFilterSql
            ";
            error_log("Uptime SQL: " . preg_replace('/\s+/', ' ', $sqlUptime) . " Params: " . json_encode($uptimeParams));
            $stmtUptime = $pdo->prepare($sqlUptime);
            $stmtUptime->execute($uptimeParams);
            $uptimeData = $stmtUptime->fetch(PDO::FETCH_ASSOC);
            if ($stmtUptime->errorCode() !== '00000') {
                error_log("Uptime SQL Error: " . json_encode($stmtUptime->errorInfo()));
            }

            $daysWithData = (int)$uptimeData['days_with_data'];
            $totalDaysInPeriod = (new DateTime($currentEndDate))->diff(new DateTime($currentStartDate))->days + 1;
            
            $avgUptime = ($totalDaysInPeriod > 0) ? ($daysWithData / $totalDaysInPeriod) * 100 : 0;
            $avgUptime = min($avgUptime, 100);

            $uptimeChange = 0; // Untuk penyederhanaan, bisa dikembangkan lebih lanjut

            // --- Menghitung persentase perubahan ---
            $powerChange = calculatePercentageChange($avgPower, $prevAvgPower);
            $energyChange = calculatePercentageChange($totalEnergy, $prevTotalEnergy);
            $costChange = calculatePercentageChange($totalCost, $prevTotalCost);

            $response = [
                'status' => 'success',
                'summary_stats' => [
                    'average_power' => ['value' => round($avgPower, 2), 'change_percent' => $powerChange],
                    'total_energy_consumption' => ['value' => round($totalEnergy, 3), 'change_percent' => $energyChange],
                    'total_cost' => ['value' => round($totalCost, 0), 'change_percent' => $costChange],
                    'average_uptime' => ['value' => round($avgUptime, 1), 'change_percent' => $uptimeChange]
                ]
            ];

            // --- Generate Insights and Recommendations ---
            // Pastikan $electricityTariffs tersedia dari config/electricity_tariffs.php
            global $electricityTariffs; // Akses variabel global
            $insightsAndRecommendations = generateInsightsAndRecommendations($userId, $deviceId, $pdo, $electricityTariffs);
            $response['insights_recommendations'] = $insightsAndRecommendations;

            break;

        case 'energy_chart':
            $chartDetails = getChartPeriodDetails($chartGranularity);
            $chartStartDate = $chartDetails['start'];
            $chartEndDate = $chartDetails['end'];
            $groupByFormat = $chartDetails['group_by_format'];
            $labelFormat = $chartDetails['label_format'];

            error_log("Energy Chart Period: [$chartStartDate to $chartEndDate], Granularity: $chartGranularity");

            $chartParams = array_merge([$chartStartDate, $chartEndDate, $userId], $deviceFilterParams);
            $sqlChart = "
                SELECT
                    DATE_FORMAT(ed_daily.created_at, '$groupByFormat') AS period_label,
                    COALESCE(SUM(ed_daily.energy_diff), 0) AS total_energy_kwh
                FROM (
                    SELECT
                        device_id,
                        created_at,
                        (MAX(energy) - MIN(energy)) AS energy_diff
                    FROM energy_data
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY device_id, DATE_FORMAT(created_at, '$groupByFormat') 
                ) ed_daily
                JOIN devices d ON ed_daily.device_id = d.id
                WHERE d.user_id = ? AND d.is_active = 1
                $deviceFilterSql
                GROUP BY period_label
                ORDER BY period_label ASC
            ";
            error_log("Energy Chart SQL: " . preg_replace('/\s+/', ' ', $sqlChart) . " Params: " . json_encode($chartParams));
            $stmtChart = $pdo->prepare($sqlChart);
            $stmtChart->execute($chartParams);
            $chartResults = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
            if ($stmtChart->errorCode() !== '00000') {
                error_log("Energy Chart SQL Error: " . json_encode($stmtChart->errorInfo()));
            }

            $dataPoints = [];
            $labels = [];

            $currentDate = new DateTime($chartStartDate);
            $endDateObj = new DateTime($chartEndDate);

            while ($currentDate <= $endDateObj) {
                $formattedLabel = $currentDate->format($labelFormat);
                if ($chartGranularity === 'weekly') {
                    $weekNum = $currentDate->format('W');
                    $yearNum = $currentDate->format('Y');
                    $formattedLabel = "Wk $weekNum, $yearNum";
                } elseif ($chartGranularity === 'hourly') { // TAMBAHAN UNTUK HOURLY
                    // Label sudah 'H:00', tidak perlu modifikasi khusus di sini
                }
                $labels[] = $formattedLabel;
                $dataPoints[$formattedLabel] = 0;
                
                if ($chartGranularity === 'daily') {
                    $currentDate->modify('+1 day');
                } elseif ($chartGranularity === 'weekly') {
                    $currentDate->modify('+1 week');
                } elseif ($chartGranularity === 'monthly') {
                    $currentDate->modify('+1 month');
                } elseif ($chartGranularity === 'hourly') { // TAMBAHAN UNTUK HOURLY
                    $currentDate->modify('+1 hour');
                } else {
                    $currentDate->modify('+1 day');
                }
            }
            
            foreach ($chartResults as $row) {
                $periodLabelRaw = $row['period_label'];
                $dbDate = null; // Inisialisasi null

                // Untuk weekly, period_label adalah 'YYYY-WW', perlu diubah ke DateTime yang benar untuk format label
                if ($chartGranularity === 'weekly') {
                    // Membuat DateTime dari YYYY-WW, asumsikan hari Senin dari minggu itu
                    $year = substr($periodLabelRaw, 0, 4);
                    $week = substr($periodLabelRaw, 5, 2);
                    $dbDate = new DateTime();
                    $dbDate->setISODate($year, $week); // Ini akan mengatur ke hari Senin minggu itu
                } elseif ($chartGranularity === 'hourly') { // TAMBAHAN UNTUK HOURLY
                    // MySQL DATE_FORMAT('%Y-%m-%d %H:00:00') akan menghasilkan string seperti '2026-05-18 10:00:00'
                    // Ini bisa langsung di-parse oleh DateTime
                    $dbDate = new DateTime($periodLabelRaw);
                } else {
                    $dbDate = new DateTime($periodLabelRaw);
                }
                
                $formattedDbLabel = '';
                if ($dbDate) { // Pastikan $dbDate tidak null sebelum memformat
                    $formattedDbLabel = $dbDate->format($labelFormat);
                    if ($chartGranularity === 'weekly') {
                        $weekNum = $dbDate->format('W');
                        $yearNum = $dbDate->format('Y');
                        $formattedDbLabel = "Wk $weekNum, $yearNum";
                    } elseif ($chartGranularity === 'hourly') { // TAMBAHAN UNTUK HOURLY
                        // Untuk hourly, label sudah 'H:00', tidak perlu modifikasi khusus di sini
                    }
                }

                if (isset($dataPoints[$formattedDbLabel])) {
                    $dataPoints[$formattedDbLabel] = round((float)$row['total_energy_kwh'], 3);
                }
            }
            
            $response = [
                'status' => 'success',
                'energy_chart_data' => [
                    'labels' => array_values($labels),
                    'data' => array_values($dataPoints)
                ]
            ];
            if (empty($dataPoints) || array_sum($dataPoints) === 0) {
                $response['message'] = 'Belum ada data konsumsi energi untuk periode ini.';
            }
            break;

        case 'device_distribution':
            $distributionPeriodRange = getSummaryPeriodRange('30days'); // Fixed 30 days for distribution
            $distStartDate = $distributionPeriodRange['start'];
            $distEndDate = $distributionPeriodRange['end'];

            error_log("Device Distribution Period: [$distStartDate to $distEndDate]");

            $distParams = array_merge([$distStartDate, $distEndDate, $userId], $deviceFilterParams);
            $sqlDist = "
                SELECT
                    d.device_name,
                    COALESCE(SUM(ed.energy_diff), 0) AS total_energy
                FROM (
                    SELECT
                        device_id,
                        (MAX(energy) - MIN(energy)) AS energy_diff,
                        created_at
                    FROM energy_data
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY device_id, DATE(created_at)
                ) ed
                JOIN devices d ON ed.device_id = d.id
                WHERE d.user_id = ? AND d.is_active = 1
                $deviceFilterSql
                GROUP BY d.id, d.device_name
                ORDER BY total_energy DESC
            ";
            error_log("Device Distribution SQL: " . preg_replace('/\s+/', ' ', $sqlDist) . " Params: " . json_encode($distParams));
            $stmtDist = $pdo->prepare($sqlDist);
            $stmtDist->execute($distParams);
            $distResults = $stmtDist->fetchAll(PDO::FETCH_ASSOC);
            if ($stmtDist->errorCode() !== '00000') {
                error_log("Device Distribution SQL Error: " . json_encode($stmtDist->errorInfo()));
            }

            $deviceLabels = [];
            $deviceData = [];
            foreach ($distResults as $row) {
                if ((float)$row['total_energy'] > 0) {
                    $deviceLabels[] = htmlspecialchars($row['device_name']);
                    $deviceData[] = round((float)$row['total_energy'], 3);
                }
            }

            $response = [
                'status' => 'success',
                'device_distribution_data' => [
                    'labels' => $deviceLabels,
                    'data' => $deviceData
                ]
            ];
            if (empty($deviceData)) {
                $response['message'] = 'Belum ada data distribusi perangkat.';
            }
            break;

        case 'daily_details':
            $detailsPeriodRange = getSummaryPeriodRange('30days'); // Fixed 30 days for details
            $detailsStartDate = $detailsPeriodRange['start'];
            $detailsEndDate = $detailsPeriodRange['end'];

            error_log("Daily Details Period: [$detailsStartDate to $detailsEndDate]");

            $detailsParams = array_merge([$detailsStartDate, $detailsEndDate, $userId], $deviceFilterParams);
            $sqlDetails = "
                SELECT
                    daily_agg.tgl,
                    d.device_name,
                    COALESCE(daily_agg.daily_energy_kwh, 0) AS daily_energy,
                    COALESCE(daily_agg.daily_energy_kwh * d.tarif_per_kwh, 0) AS daily_cost,
                    CASE
                        WHEN SUM(CASE WHEN ed_status.machine_status = 'ON' THEN 1 ELSE 0 END) > 0 THEN 'ON'
                        WHEN SUM(CASE WHEN ed_status.machine_status = 'OFF' THEN 1 ELSE 0 END) > 0 THEN 'OFF'
                        ELSE 'UNKNOWN'
                    END AS machine_status
                FROM (
                    SELECT
                        device_id,
                        DATE(created_at) AS tgl,
                        (MAX(energy) - MIN(energy)) AS daily_energy_kwh
                    FROM energy_data
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY device_id, DATE(created_at)
                ) daily_agg
                JOIN devices d ON daily_agg.device_id = d.id
                LEFT JOIN energy_data ed_status ON daily_agg.device_id = ed_status.device_id AND daily_agg.tgl = DATE(ed_status.created_at)
                WHERE d.user_id = ? AND d.is_active = 1
                $deviceFilterSql
                GROUP BY daily_agg.tgl, d.id, d.device_name, d.tarif_per_kwh
                ORDER BY daily_agg.tgl DESC, d.device_name ASC
                LIMIT 100
            ";
            error_log("Daily Details SQL: " . preg_replace('/\s+/', ' ', $sqlDetails) . " Params: " . json_encode($detailsParams));
            $stmtDetails = $pdo->prepare($sqlDetails);
            $stmtDetails->execute($detailsParams);
            $detailsResults = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
            if ($stmtDetails->errorCode() !== '00000') {
                error_log("Daily Details SQL Error: " . json_encode($stmtDetails->errorInfo()));
            }

            $dailyConsumptionDetails = [];
            foreach ($detailsResults as $row) {
                $date = new DateTime($row['tgl']);
                $dailyConsumptionDetails[] = [
                    'date' => $date->format('d/m/Y'),
                    'device_name' => htmlspecialchars($row['device_name']),
                    'energy_kwh' => round((float)$row['daily_energy'], 3),
                    'cost_rp' => round((float)$row['daily_cost'], 0),
                    'machine_status' => $row['machine_status']
                ];
            }

            $response = [
                'status' => 'success',
                'daily_consumption_details' => $dailyConsumptionDetails
            ];
            if (empty($dailyConsumptionDetails)) {
                $response['message'] = 'Belum ada detail konsumsi harian untuk periode ini.';
            }
            break;

        default:
            $response['message'] = 'Invalid request type.';
            error_log("Analytics API: Invalid request type '$requestType'.");
            break;
    }

} catch (PDOException $e) {
    error_log("API Error (get_analytics_data): " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
exit();

// --- Fungsi Bantuan ---

function calculatePercentageChange($current, $previous) {
    if ($previous == 0) {
        return 0; 
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function getSummaryPeriodRange($period) {
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
        case 'this_month':
            $start->modify('first day of this month');
            break;
        case 'last_month':
            $start->modify('first day of last month');
            $end = (new DateTime('first day of this month'))->modify('-1 second');
            break;
        default:
            $start->modify('-30 days'); // Default to 30 days if period is unknown
    }

    return [
        'start' => $start->format('Y-m-d 00:00:00'),
        'end' => $end->format('Y-m-d 23:59:59')
    ];
}

function getPreviousSummaryPeriodRange($period) {
    $currentPeriodRange = getSummaryPeriodRange($period);
    $currentStartDate = new DateTime($currentPeriodRange['start']);
    $currentEndDate = new DateTime($currentPeriodRange['end']);

    // Calculate the duration of the current period in days
    $interval = $currentStartDate->diff($currentEndDate);
    $days = $interval->days + 1;

    // The previous period should end the day before the current period starts
    $prevEnd = clone $currentStartDate;
    $prevEnd->modify('-1 day'); // Go to the day before current start

    // The previous period should start 'days' days before prevEnd
    $prevStart = clone $prevEnd;
    $prevStart->modify("-{$days} days +1 second"); // Start of the day 'days' days before prevEnd

    return [
        'start' => $prevStart->format('Y-m-d 00:00:00'),
        'end' => $prevEnd->format('Y-m-d 23:59:59')
    ];
}

function getChartPeriodDetails($granularity) {
    $now = new DateTime();
    $end = clone $now;
    $start = clone $now;
    $groupByFormat = '%Y-%m-%d';
    $labelFormat = 'd M';

    switch ($granularity) {
        case 'daily':
            $start->modify('-30 days');
            $groupByFormat = '%Y-%m-%d';
            $labelFormat = 'd M';
            break;
        case 'weekly':
            $start->modify('-12 weeks');
            $groupByFormat = '%Y-%v'; // %Y untuk tahun, %v untuk nomor minggu (01-53)
            $labelFormat = 'W Y'; // Akan diformat ulang di PHP untuk lebih deskriptif
            break;
        case 'monthly':
            $start->modify('-12 months');
            $groupByFormat = '%Y-%m';
            $labelFormat = 'M Y';
            break;
        case 'hourly': // <-- TAMBAHAN UNTUK HOURLY
            $start->modify('-24 hours'); // Tampilkan data 24 jam terakhir
            $groupByFormat = '%Y-%m-%d %H:00:00'; // Group by jam (contoh: 2026-05-18 10:00:00)
            $labelFormat = 'H:00'; // Label jam (misal: 10:00)
            break;
        default:
            $start->modify('-30 days');
            $groupByFormat = '%Y-%m-%d';
            $labelFormat = 'd M';
    }

    return [
        'start' => $start->format('Y-m-d H:00:00'), // Sesuaikan format start/end agar sesuai dengan granularitas jam
        'end' => $end->format('Y-m-d H:59:59'),     // Sesuaikan format start/end agar sesuai dengan granularitas jam
        'group_by_format' => $groupByFormat,
        'label_format' => $labelFormat
    ];
}
