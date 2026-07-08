<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

if (!defined('DIR')) define('DIR', __DIR__);
require_once DIR . '/../config/database.php';

function writeAnomalyLog($message){
    $logFile = DIR . '/../logs/anomaly_detection.log';
    if (!is_dir(dirname($logFile))) mkdir(dirname($logFile),0777,true);
    
    // Log rotation: jika file > 10MB, rename ke file lama
    if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
        $oldLog = DIR . '/../logs/anomaly_detection_old.log';
        if (file_exists($oldLog)) unlink($oldLog);
        rename($logFile, $oldLog);
    }
    
    file_put_contents($logFile,"[".date('Y-m-d H:i:s')."] ".$message.PHP_EOL,FILE_APPEND);
}

function calculateMedian(array $values){
    if(empty($values)) return 0;
    sort($values);
    $count=count($values);
    $mid=floor($count/2);
    return ($count % 2) ? $values[$mid] : (($values[$mid-1]+$values[$mid])/2);
}

/**
 * Mendapatkan baseline daya untuk suatu perangkat.
 * Strategi:
 *   1) Jika tersedia >= 3 hari data (masing-masing >= 6 data point),
 *      gunakan median dari rata-rata daya harian (30 hari terakhir).
 *   2) Fallback: rata-rata daya 24 jam terakhir (untuk perangkat baru).
 *   3) Jika tidak ada data sama sekali, return null (skip deteksi).
 */
function getBaseline($pdo, $deviceId) {
    // Strategi 1: Median dari rata-rata harian (30 hari)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as tgl, AVG(power) as daily_avg
        FROM energy_data
        WHERE device_id = ?
          AND machine_status = 'ON'
          AND created_at >= NOW() - INTERVAL 30 DAY
        GROUP BY DATE(created_at)
        HAVING COUNT(*) >= 6
        ORDER BY tgl
    ");
    $stmt->execute([$deviceId]);
    $dailyAvgs = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN, 1));

    if (count($dailyAvgs) >= 3) {
        $baseline = calculateMedian($dailyAvgs);
        writeAnomalyLog("Device $deviceId: Baseline from ".count($dailyAvgs)." days median daily avg = ".round($baseline,2)."W");
        return $baseline;
    }

    // Strategi 2 (Fallback): Rata-rata 24 jam terakhir untuk perangkat baru
    $stmt2 = $pdo->prepare("
        SELECT AVG(power)
        FROM energy_data
        WHERE device_id = ?
          AND machine_status = 'ON'
          AND created_at >= NOW() - INTERVAL 24 HOUR
    ");
    $stmt2->execute([$deviceId]);
    $fallback = (float) $stmt2->fetchColumn();

    if ($fallback > 0) {
        writeAnomalyLog("Device $deviceId: Fallback baseline (24h avg) = ".round($fallback,2)."W");
        return $fallback;
    }

    writeAnomalyLog("Device $deviceId: No data available for baseline calculation. Skipping.");
    return null;
}

/**
 * Deteksi anomali menggunakan Z-Score dari moving window (20 data terakhir).
 * Metode general yang bekerja untuk semua jenis beban.
 * 
 * @param object $pdo Koneksi database
 * @param int $deviceId ID perangkat
 * @param float $currentAvgPower Rata-rata daya saat ini
 * @param float $currentMaxPower Daya puncak saat ini
 * @return array|null Array berisi statistik, atau null jika data tidak cukup
 */
function detectAnomalyGeneral($pdo, $deviceId, $currentAvgPower, $currentMaxPower) {
    // Ambil 20 data point terakhir (sekitar 10-20 menit, tergantung interval)
    $stmt = $pdo->prepare("
        SELECT power
        FROM energy_data
        WHERE device_id = ?
          AND machine_status = 'ON'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$deviceId]);
    $recentPowers = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    
    if (count($recentPowers) < 5) {
        return null; // Belum cukup data
    }
    
    // Hitung mean dan standar deviasi dari window
    $mean = array_sum($recentPowers) / count($recentPowers);
    
    $variance = 0;
    foreach ($recentPowers as $p) {
        $variance += pow($p - $mean, 2);
    }
    $stdDev = sqrt($variance / count($recentPowers));
    
    // Hindari division by zero
    if ($stdDev < 1) $stdDev = 1;
    
    // Hitung Z-Score untuk data saat ini
    $zScore = ($currentAvgPower - $mean) / $stdDev;
    
    // Deteksi Spike: Bandingkan max_power dengan mean window
    $spikeRatio = ($mean > 0) ? $currentMaxPower / $mean : 0;
    
    return [
        'mean' => $mean,
        'stdDev' => $stdDev,
        'zScore' => $zScore,
        'spikeRatio' => $spikeRatio
    ];
}

/**
 * Cek apakah daya sedang dalam tren menurun (charging cycle)
 * Jika tren menurun signifikan, kemungkinan ini charger laptop/baterai, bukan anomali
 */
function isDecliningTrend($pdo, $deviceId) {
    $stmt = $pdo->prepare("
        SELECT power, created_at
        FROM energy_data
        WHERE device_id = ?
          AND machine_status = 'ON'
          AND created_at >= NOW() - INTERVAL 10 MINUTE
        ORDER BY created_at ASC
    ");
    $stmt->execute([$deviceId]);
    $recentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($recentData);
    if ($count < 3) {
        return false;
    }
    
    $firstPower = (float)$recentData[0]['power'];
    $lastPower = (float)$recentData[$count - 1]['power'];
    $trend = $lastPower - $firstPower;
    
    // Jika daya turun lebih dari 5W dalam 10 menit, anggap ini charging normal
    return $trend < -5;
}

/**
 * Cek apakah alert sudah ada dalam periode tertentu (deduplikasi).
 * Periode deduplikasi berbeda per jenis anomali.
 */
function isDuplicateAlert($pdo, $deviceId, $severity, $type) {
    switch ($type) {
        case 'leakage':
        case 'relay_failure':
            $interval = '2 MINUTE';
            break;
        case 'warning':
            $interval = '15 MINUTE';
            break;
        default:
            $interval = '5 MINUTE';
    }
    
    $chk = $pdo->prepare("SELECT id FROM alerts WHERE device_id=? AND severity=? AND type=? AND created_at>=NOW()-INTERVAL $interval");
    $chk->execute([$deviceId, $severity, $type]);
    return $chk->fetch() ? true : false;
}

/**
 * Kirim notifikasi real-time untuk kejadian penting.
 */
function sendCriticalNotification($pdo, $device, $type, $message) {
    writeAnomalyLog("NOTIFICATION: Device {$device['device_id']} ({$device['device_name']}) | User: {$device['full_name']} ({$device['email']}) | $type | $message");
}

$response=['status'=>'error','message'=>'Anomaly detection failed'];

try{
    $pdo=getPDOConnection();

    writeAnomalyLog("=== Detection Started ===");

    // Ambil semua perangkat aktif
    $sql="
    SELECT DISTINCT
        d.id AS device_id,
        d.device_name,
        d.user_id,
        u.full_name,
        u.email,
        COALESCE(d.anomaly_threshold_percent,30) anomaly_threshold_percent,
        COALESCE(d.spike_threshold_percent,180) spike_threshold_percent,
        COALESCE(d.auto_shutdown_overload,0) auto_shutdown_overload,
        COALESCE(d.auto_shutdown_standby,0) auto_shutdown_standby,
        COALESCE(d.standby_threshold_watt,5) standby_threshold_watt,
        d.relay_state
    FROM devices d
    JOIN users u ON d.user_id=u.id
    WHERE d.is_active=1";
    $devices=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach($devices as $device){

        $deviceId=$device['device_id'];

        // === Hitung Baseline ===
        $baseline = getBaseline($pdo, $deviceId);
        if ($baseline === null) {
            continue;
        }

        // === Data 5 Menit Terakhir ===
        $stmt=$pdo->prepare("
            SELECT AVG(power) avg_power, MAX(max_power) max_power,
                   AVG(current) avg_current
            FROM energy_data
            WHERE device_id=?
            AND machine_status='ON'
            AND created_at>=NOW()-INTERVAL 5 MINUTE
        ");
        $stmt->execute([$deviceId]);
        $curr=$stmt->fetch(PDO::FETCH_ASSOC);

        $avgPower=(float)($curr['avg_power'] ?? 0);
        $maxPower=(float)($curr['max_power'] ?? 0);

        // === Deteksi Anomali (Saat Relay ON) ===
        if($baseline>0 && $avgPower>0){

            $diff=(($avgPower-$baseline)/$baseline)*100;
            $severity=null;
            $type='overload';
            $message='';

            // ============================================================
            // METODE UTAMA: Deteksi dengan Z-Score (general untuk semua beban)
            // ============================================================
            $stats = detectAnomalyGeneral($pdo, $deviceId, $avgPower, $maxPower);
            
            if ($stats !== null) {
                
                // Log statistik untuk debugging
                writeAnomalyLog("Device $deviceId | Z-Score: ".round($stats['zScore'], 2)." | Mean window: ".round($stats['mean'], 2)."W | StdDev: ".round($stats['stdDev'], 2)." | SpikeRatio: ".round($stats['spikeRatio'], 2));
                
                // Prioritas 1: Spike (lonjakan puncak ekstrem) — spikeRatio > 3.0
                if ($stats['spikeRatio'] > 3.0) {
                    $type = 'spike';
                    $severity = 'critical';
                    $message = "Terdeteksi lonjakan daya tinggi pada perangkat '{$device['device_name']}'! Daya puncak mencapai **".round($maxPower,2)."W**, yaitu " . round($stats['spikeRatio'], 1) . "x lipat dari rata-rata ({$stats['mean']}W). Ini bisa menandakan korsleting atau masalah serius. Mohon periksa perangkat segera!";
                }
                // Prioritas 2: Overload critical — Z-Score > 3.0
                elseif ($stats['zScore'] > 3.0) {
                    $type = 'overload';
                    $severity = 'critical';
                    $message = "PERINGATAN KRITIS: Beban berlebih pada perangkat '{$device['device_name']}'! Konsumsi daya {$avgPower}W berada " . round($stats['zScore'], 1) . " standar deviasi di atas normal (rata-rata {$stats['mean']}W). Ini dapat merusak perangkat. Mohon segera periksa!";
                }
                // Prioritas 3: Warning — Z-Score antara 2.0 dan 3.0
                elseif ($stats['zScore'] > 2.0) {
                    $type = 'overload';
                    $severity = 'warning';
                    $message = "PERINGATAN: Peningkatan daya pada perangkat '{$device['device_name']}'. Konsumsi daya {$avgPower}W meningkat di atas normal (rata-rata {$stats['mean']}W). Pantau perangkat Anda untuk menghindari beban berlebih.";
                }
            }
              
            if ($severity === null) {
                // Ambil threshold spike per perangkat (default 180%)
                $spikeThreshold = (float)($device['spike_threshold_percent'] ?? 180);

                if($maxPower > ($baseline * ($spikeThreshold / 100))){
                    $type='spike';
                    $severity='critical';
                    $message="Terdeteksi lonjakan daya tinggi pada perangkat '{$device['device_name']}'! Daya puncak mencapai **".round($maxPower,2)."W**, melebihi batas normal ({$spikeThreshold}% dari baseline ".round($baseline,2)."W). Ini bisa menandakan masalah atau aktivitas tidak biasa. Mohon periksa perangkat.";
                    
                } elseif($diff > $device['anomaly_threshold_percent']){
                    $severity='critical';
                    $message="PERINGATAN KRITIS: Beban berlebih pada perangkat '{$device['device_name']}'! Konsumsi daya meningkat drastis sebesar **".round($diff,2)."%** di atas baseline ".round($baseline,2)."W. Ini dapat merusak perangkat atau menyebabkan pemadaman. Mohon segera periksa!";
                } elseif($diff > ($device['anomaly_threshold_percent']/2)){
                    $severity='warning';
                    $message="PERINGATAN: Peningkatan daya pada perangkat '{$device['device_name']}'. Konsumsi daya naik **".round($diff,2)."%** di atas baseline ".round($baseline,2)."W. Pantau perangkat Anda untuk menghindari beban berlebih.";
                }
            }

            if($severity){
                if ($type === 'overload' || $type === 'spike') {
                    if (isDecliningTrend($pdo, $deviceId)) {
                        writeAnomalyLog("Device $deviceId: Skipped $type alert - declining trend detected (likely normal charging). Power: ".round($avgPower,2)."W, Mean window: ".round($stats['mean'] ?? $baseline, 2)."W, Z-Score: ".round($stats['zScore'] ?? 0, 2));
                        continue;
                    }
                }
                
                if(!isDuplicateAlert($pdo, $deviceId, $severity, $type)){
                    
                    $ins=$pdo->prepare("
                    INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?,?,?,?,NOW())");
                    $ins->execute([
                        $device['user_id'],
                        $deviceId,
                        $type,
                        $message,
                        $severity
                    ]);

                    writeAnomalyLog("Alert created: Device $deviceId | $severity | $type | Z-Score: ".round($stats['zScore'] ?? 0, 2)." | $message");

                    if ($severity === 'critical') {
                        sendCriticalNotification($pdo, $device, $type, $message);
                    }

                    if($severity==='critical' && $device['auto_shutdown_overload']){
                        
                        $countAlert = $pdo->prepare("
                            SELECT COUNT(*) FROM alerts 
                            WHERE device_id=? AND severity='critical' 
                            AND created_at>=NOW()-INTERVAL 10 MINUTE
                        ");
                        $countAlert->execute([$deviceId]);
                        $totalCritical = (int) $countAlert->fetchColumn();

                        if ($totalCritical >= 3) {
                            $off=$pdo->prepare("UPDATE devices SET relay_state='off',last_relay_command_at=NOW() WHERE id=?");
                            $off->execute([$deviceId]);
                            $autoShutdownMessage = "Perangkat '{$device['device_name']}' telah dimatikan secara otomatis karena terdeteksi {$totalCritical} peringatan kritis dalam 10 menit terakhir untuk mencegah kerusakan lebih lanjut.";
                            writeAnomalyLog("Auto-shutdown: Device $deviceId turned OFF after $totalCritical critical alerts. Message: " . $autoShutdownMessage);
                            sendCriticalNotification($pdo, $device, 'auto_shutdown', $autoShutdownMessage);
                        } else {
                            writeAnomalyLog("Auto-shutdown pending: Device $deviceId has $totalCritical/3 critical alerts.");
                        }
                    }
                }
            }
        }

        // === Deteksi Arus Bocor dan Kegagalan Relay (Saat Relay OFF) ===
        if($device['relay_state']==='off'){
            $stmt=$pdo->prepare("
                SELECT AVG(power) avg_power, AVG(current) avg_current
                FROM energy_data
                WHERE device_id=?
                AND machine_status='OFF'
                AND created_at>=NOW()-INTERVAL 10 MINUTE
            ");
            $stmt->execute([$deviceId]);
            $offData=$stmt->fetch(PDO::FETCH_ASSOC);

            $avgPower=(float)($offData['avg_power'] ?? 0);
            $avgCurrent=(float)($offData['avg_current'] ?? 0);

            // === Deteksi Arus Bocor dengan Level ===
            if($avgCurrent >= 0.05){
                if(!isDuplicateAlert($pdo, $deviceId, 'critical', 'leakage')){
                    $leakageCriticalMessage = "BAHAYA! Arus bocor kritis terdeteksi pada perangkat '{$device['device_name']}' sebesar **".round($avgCurrent,3)."A**. Ini berisiko tinggi menyebabkan sengatan listrik atau kebakaran. **MATIKAN SUMBER DAYA UTAMA SEGERA** dan hubungi teknisi listrik!";
                    $pdo->prepare("INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?, 'leakage', ?, 'critical', NOW())")
                    ->execute([
                        $device['user_id'],
                        $deviceId,
                        $leakageCriticalMessage
                    ]);
                    writeAnomalyLog("CRITICAL leakage current for device $deviceId: ".round($avgCurrent,3)."A. Message: " . $leakageCriticalMessage);
                    sendCriticalNotification($pdo, $device, 'leakage_critical', $leakageCriticalMessage);
                }
            } elseif($avgCurrent >= 0.01 && $avgCurrent < 0.05) {
                if(!isDuplicateAlert($pdo, $deviceId, 'warning', 'leakage')){
                    $leakageWarningMessage = "PERINGATAN: Arus bocor kecil terdeteksi pada perangkat '{$device['device_name']}' sebesar **".round($avgCurrent,3)."A**. Ini mungkin disebabkan oleh pelepasan kapasitor normal, namun tetap disarankan untuk memantau. Jika terus berlanjut atau meningkat, periksa instalasi.";
                    $pdo->prepare("INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?, 'leakage', ?, 'warning', NOW())")
                    ->execute([
                        $device['user_id'],
                        $deviceId,
                        $leakageWarningMessage
                    ]);
                    writeAnomalyLog("Warning leakage current for device $deviceId: ".round($avgCurrent,3)."A. Message: " . $leakageWarningMessage);
                }
            }

            // === Deteksi Kegagalan Relay dengan Persentase ===
            $relayFailureThreshold = max(20, $baseline * 0.05);
            if($avgPower > $relayFailureThreshold){
                if(!isDuplicateAlert($pdo, $deviceId, 'critical', 'relay_failure')){
                    $relayFailureMessage = "GAGAL RELAY: Perangkat '{$device['device_name']}' terdeteksi masih mengonsumsi daya sebesar **".round($avgPower,2)."W** meskipun relay dalam posisi OFF (batas toleransi ".round($relayFailureThreshold,2)."W). Ini menunjukkan relay tidak berfungsi dengan baik. Mohon periksa perangkat.";
                    $pdo->prepare("INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?, 'relay_failure', ?, 'critical', NOW())")
                    ->execute([
                        $device['user_id'],
                        $deviceId,
                        $relayFailureMessage
                    ]);
                    writeAnomalyLog("Relay failure detected for device $deviceId: ".round($avgPower,2)."W power still flowing. Message: " . $relayFailureMessage);
                    sendCriticalNotification($pdo, $device, 'relay_failure', $relayFailureMessage);
                }
            }
        }
    }

    $response=['status'=>'success','message'=>'Detection completed'];

}catch(Exception $e){
    writeAnomalyLog("Error: ".$e->getMessage());
    $response['message']='Error: '.$e->getMessage();
}

echo json_encode($response);
?>
