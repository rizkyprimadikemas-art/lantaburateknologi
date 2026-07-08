<?php
// File: config/electricity_tariffs.php

// Fungsi untuk mendapatkan pengaturan dari database
function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// Ambil koneksi PDO
require_once __DIR__ . '/database.php';
$pdo = getPDOConnection();

// Ambil tarif listrik dari database
$kwh_rate_str = getSetting($pdo, 'kwh_rate', '1444.70');
$fixed_monthly_charge_str = getSetting($pdo, 'fixed_monthly_charge', '0');
$has_time_of_use_tariff_str = getSetting($pdo, 'has_time_of_use_tariff', 'false');
$peak_hour_rate_multiplier_str = getSetting($pdo, 'peak_hour_rate_multiplier', '1.5');
$peak_hour_start_str = getSetting($pdo, 'peak_hour_start', '18:00');
$peak_hour_end_str = getSetting($pdo, 'peak_hour_end', '22:00');


// Konversi ke tipe data yang sesuai
$electricityTariffs = [
    'kwh_rate'                  => (float)$kwh_rate_str,
    'fixed_monthly_charge'      => (float)$fixed_monthly_charge_str,
    'has_time_of_use_tariff'    => filter_var($has_time_of_use_tariff_str, FILTER_VALIDATE_BOOLEAN),
    'peak_hour_rate_multiplier' => (float)$peak_hour_rate_multiplier_str,
    'peak_hour_start'           => $peak_hour_start_str,
    'peak_hour_end'             => $peak_hour_end_str,
];

return $electricityTariffs;
