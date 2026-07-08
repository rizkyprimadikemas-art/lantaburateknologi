<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

if (!defined('DIR')) define('DIR', __DIR__);
require_once DIR . '/../config/database.php';

function writeAnomalyLog($message){
    $logFile = DIR . '/../logs/anomaly_detection.log';
    if (!is_dir(dirname($logFile))) mkdir(dirname($logFile),0777,true);
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

    // Tidak ada data sama sekali
    writeAnomalyLog("Device $deviceId: No data available for baseline calculation. Skipping.");
    return null;
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
            // Skip deteksi untuk perangkat ini
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

            // Deteksi Spike
            if($maxPower > ($baseline*1.8)){
                $type='spike';
                $severity='critical';
                $message="SPIKE DETECTED. Peak ".round($maxPower,2)."W melebihi baseline ".round($baseline,2)."W.";
                
            } elseif($diff > $device['anomaly_threshold_percent']){
                // Deteksi Overload
                $severity='critical';
                $message="OVERLOAD DETECTED. Increase ".round($diff,2)."%.";
            } elseif($diff > ($device['anomaly_threshold_percent']/2)){
                $severity='warning';
                $message="Power warning. Increase ".round($diff,2)."%.";
            }

            if($severity){
                // Cek apakah alert serupa sudah ada dalam 5 menit terakhir
                $chk=$pdo->prepare("SELECT id FROM alerts WHERE device_id=? AND severity=? AND type=? AND created_at>=NOW()-INTERVAL 5 MINUTE");
                $chk->execute([$deviceId,$severity,$type]);

                if(!$chk->fetch()){
                    // Jika belum ada, masukkan alert baru
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

                    writeAnomalyLog("Alert created: Device $deviceId | $severity | $type | $message");

                    // Jika critical dan auto_shutdown_overload aktif, matikan relay
                    if($severity==='critical' && $device['auto_shutdown_overload']){
                        $off=$pdo->prepare("UPDATE devices SET relay_state='off',last_relay_command_at=NOW() WHERE id=?");
                        $off->execute([$deviceId]);
                        writeAnomalyLog("Auto-shutdown: Device $deviceId turned OFF due to $type anomaly.");
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

            // Deteksi Arus Bocor
            if($avgCurrent >= 0.05){
                $chk=$pdo->prepare("SELECT id FROM alerts WHERE device_id=? AND severity='critical' AND type='leakage' AND created_at>=NOW()-INTERVAL 5 MINUTE");
                $chk->execute([$deviceId]);
                if(!$chk->fetch()){
                    $pdo->prepare("INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?, 'leakage', ?, 'critical', NOW())")
                    ->execute([
                        $device['user_id'],
                        $deviceId,
                        "Leakage current detected: ".round($avgCurrent,3)."A"
                    ]);
                    writeAnomalyLog("Leakage current detected for device $deviceId: ".round($avgCurrent,3)."A");
                }
            }

            // Deteksi Kegagalan Relay
            if($avgPower > 20){
                $chk=$pdo->prepare("SELECT id FROM alerts WHERE device_id=? AND severity='critical' AND type='relay_failure' AND created_at>=NOW()-INTERVAL 5 MINUTE");
                $chk->execute([$deviceId]);
                if(!$chk->fetch()){
                    $pdo->prepare("INSERT INTO alerts(user_id,device_id,type,message,severity,created_at)
                    VALUES(?,?, 'relay_failure', ?, 'critical', NOW())")
                    ->execute([
                        $device['user_id'],
                        $deviceId,
                        "Relay OFF but power still ".round($avgPower,2)."W"
                    ]);
                    writeAnomalyLog("Relay failure detected for device $deviceId: ".round($avgPower,2)."W power still flowing.");
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
