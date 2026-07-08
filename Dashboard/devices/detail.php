<?php
// File: Dashboard/devices/detail.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Perbaikan path: naik dua tingkat direktori dari Dashboard/devices/
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$userEmail = $currentUser['email'] ?? 'email@example.com';

// Menggunakan $device_db_id untuk konsistensi
$device_db_id = $_GET['id'] ?? null;

if (!$device_db_id) {
    $_SESSION['error_message'] = "ID Perangkat tidak ditemukan.";
    header('Location: list.php');
    exit();
}

$device = null;
// Inisialisasi variabel pesan untuk POST submission (sekarang menggunakan session untuk konsistensi)
// $message = '';
// $messageType = '';

try {
    $pdo = getPDOConnection();

    // --- PENANGANAN POST REQUEST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Cek tipe formulir yang disubmit
        $form_type = $_POST['form_type'] ?? '';

        if ($form_type === 'relay_control_settings') {
            // --- PENANGANAN PENGATURAN KONTROL RELAY ---
            $is_active = isset($_POST['is_active']) ? 1 : 0; // Tetap di sini karena mempengaruhi operasi relay
            $anomaly_threshold_percent = filter_var($_POST['anomaly_threshold_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
            $auto_shutdown_standby = isset($_POST['auto_shutdown_standby']) ? 1 : 0;
            $standby_threshold_watt = filter_var($_POST['standby_threshold_watt'] ?? 0, FILTER_VALIDATE_FLOAT);
            $standby_detection_duration_minutes = filter_var($_POST['standby_detection_duration_minutes'] ?? 0, FILTER_VALIDATE_INT);
            $auto_shutdown_overload = isset($_POST['auto_shutdown_overload']) ? 1 : 0;
            // START: Penambahan spike_threshold_percent
            $spike_threshold_percent = filter_var($_POST['spike_threshold_percent'] ?? 180.0, FILTER_VALIDATE_FLOAT);
            // END: Penambahan spike_threshold_percent
            
            // Validasi sederhana
            if ($anomaly_threshold_percent === false || $standby_threshold_watt === false || $standby_detection_duration_minutes === false || $spike_threshold_percent === false) { // Tambah validasi untuk spike_threshold_percent
                $_SESSION['error_message'] = 'Nilai ambang batas dan durasi harus berupa angka yang valid.';
                $_SESSION['open_relay_settings_modal'] = true; // Re-open modal on error
            } else {
                // Perbarui data perangkat di database (hanya kolom yang relevan dengan kontrol relay)
                $updateStmt = $pdo->prepare("
                    UPDATE devices SET
                    anomaly_threshold_percent = ?,
                    spike_threshold_percent = ?, -- Tambah kolom spike_threshold_percent
                    auto_shutdown_standby = ?,
                    standby_threshold_watt = ?,
                    standby_detection_duration_minutes = ?,
                    auto_shutdown_overload = ?,
                    updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $success = $updateStmt->execute([
                    $anomaly_threshold_percent,
                    $spike_threshold_percent, // Tambah nilai spike_threshold_percent
                    $auto_shutdown_standby,
                    $standby_threshold_watt,
                    $standby_detection_duration_minutes,
                    $auto_shutdown_overload,
                    $device_db_id,
                    $userId
                ]);

                if ($success) {
                    $_SESSION['success_message'] = 'Pengaturan kontrol relay berhasil diperbarui.';
                    // Refresh device data after update to show latest values
                    // Redirect to self to prevent form resubmission on refresh
                    header("Location: detail.php?id=" . $device_db_id);
                    exit();
                } else {
                    $_SESSION['error_message'] = 'Gagal memperbarui pengaturan kontrol relay.';
                    $_SESSION['open_relay_settings_modal'] = true; // Re-open modal on error
                }
            }
            // Redirect to self to prevent form resubmission on refresh
            header("Location: detail.php?id=" . $device_db_id);
            exit();

        } elseif ($form_type === 'manual_relay_control') { // Handle manual relay control
            $command = $_POST['command'] ?? '';
            if ($command === 'on' || $command === 'off') {
                $updateRelayStmt = $pdo->prepare("
                    UPDATE devices SET
                    relay_state = ?,
                    last_relay_command_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $success = $updateRelayStmt->execute([
                    $command,
                    $device_db_id,
                    $userId
                ]);

                if ($success) {
                    $_SESSION['success_message'] = 'Perintah relay "' . strtoupper($command) . '" berhasil dikirim.';
                } else {
                    $_SESSION['error_message'] = 'Gagal mengirim perintah relay "' . strtoupper($command) . '".';
                }
            } else {
                $_SESSION['error_message'] = 'Perintah relay tidak valid.';
            }
            header("Location: detail.php?id=" . $device_db_id);
            exit();
        }
        elseif ($form_type === 'schedule_management') {
            // --- PENANGANAN MANAJEMEN JADWAL ---
            $schedule_action = $_POST['schedule_action'] ?? '';

            if ($schedule_action === 'add') {
                $schedule_name = trim($_POST['schedule_name'] ?? '');
                $start_time = trim($_POST['start_time'] ?? '');
                $end_time = trim($_POST['end_time'] ?? '');
                $action = trim($_POST['action'] ?? ''); // 'on' or 'off'
                $repeat_days_array = $_POST['repeat_days'] ?? [];
                $repeat_days = 0;
                foreach ($repeat_days_array as $day_bit) {
                    $repeat_days |= (int)$day_bit;
                }
                $is_active_schedule = isset($_POST['is_active_schedule']) ? 1 : 0;

                if (empty($schedule_name) || empty($start_time) || empty($end_time) || empty($action)) {
                    $_SESSION['error_message'] = 'Semua bidang jadwal harus diisi.';
                } else {
                    $insertScheduleStmt = $pdo->prepare("
                        INSERT INTO device_schedules (device_id, user_id, schedule_name, start_time, end_time, action, repeat_days, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $success = $insertScheduleStmt->execute([
                        $device_db_id, $userId, $schedule_name, $start_time, $end_time, $action, $repeat_days, $is_active_schedule
                    ]);
                    if ($success) {
                        $_SESSION['success_message'] = 'Jadwal berhasil ditambahkan.';
                    } else {
                        $_SESSION['error_message'] = 'Gagal menambahkan jadwal.';
                    }
                }
            } elseif ($schedule_action === 'edit') {
                $schedule_id = filter_var($_POST['schedule_id'] ?? 0, FILTER_VALIDATE_INT);
                $schedule_name = trim($_POST['schedule_name'] ?? '');
                $start_time = trim($_POST['start_time'] ?? '');
                $end_time = trim($_POST['end_time'] ?? '');
                $action = trim($_POST['action'] ?? '');
                $repeat_days_array = $_POST['repeat_days'] ?? [];
                $repeat_days = 0;
                foreach ($repeat_days_array as $day_bit) {
                    $repeat_days |= (int)$day_bit;
                }
                $is_active_schedule = isset($_POST['is_active_schedule']) ? 1 : 0;

                if (!$schedule_id || empty($schedule_name) || empty($start_time) || empty($end_time) || empty($action)) {
                    $_SESSION['error_message'] = 'ID Jadwal atau bidang jadwal tidak valid.';
                } else {
                    $updateScheduleStmt = $pdo->prepare("
                        UPDATE device_schedules SET
                        schedule_name = ?, start_time = ?, end_time = ?, action = ?, repeat_days = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ? AND device_id = ? AND user_id = ?
                    ");
                    $success = $updateScheduleStmt->execute([
                        $schedule_name, $start_time, $end_time, $action, $repeat_days, $is_active_schedule,
                        $schedule_id, $device_db_id, $userId
                    ]);
                    if ($success) {
                        $_SESSION['success_message'] = 'Jadwal berhasil diperbarui.';
                    } else {
                        $_SESSION['error_message'] = 'Gagal memperbarui jadwal.';
                    }
                }
            } elseif ($schedule_action === 'delete') {
                $schedule_id = filter_var($_POST['schedule_id'] ?? 0, FILTER_VALIDATE_INT);
                if (!$schedule_id) {
                    $_SESSION['error_message'] = 'ID Jadwal tidak valid.';
                } else {
                    $deleteScheduleStmt = $pdo->prepare("
                        DELETE FROM device_schedules WHERE id = ? AND device_id = ? AND user_id = ?
                    ");
                    $success = $deleteScheduleStmt->execute([$schedule_id, $device_db_id, $userId]);
                    if ($success) {
                        $_SESSION['success_message'] = 'Jadwal berhasil dihapus.';
                    } else {
                        $_SESSION['error_message'] = 'Gagal menghapus jadwal.';
                    }
                }
            } elseif ($schedule_action === 'toggle_active') {
                $schedule_id = filter_var($_POST['schedule_id'] ?? 0, FILTER_VALIDATE_INT);
                $is_active_schedule = isset($_POST['is_active_schedule']) ? 1 : 0; // The new state

                if (!$schedule_id) {
                    $_SESSION['error_message'] = 'ID Jadwal tidak valid.';
                } else {
                    $toggleScheduleStmt = $pdo->prepare("
                        UPDATE device_schedules SET is_active = ?, updated_at = NOW()
                        WHERE id = ? AND device_id = ? AND user_id = ?
                    ");
                    $success = $toggleScheduleStmt->execute([$is_active_schedule, $schedule_id, $device_db_id, $userId]);
                    if ($success) {
                        $_SESSION['success_message'] = 'Status jadwal berhasil diperbarui.';
                    } else {
                        $_SESSION['error_message'] = 'Gagal memperbarui status jadwal.';
                    }
                }
            }
            // Redirect to self to clear POST data and show message, and re-open the modal
            $_SESSION['open_schedule_modal'] = true; // Flag to reopen modal
            header("Location: detail.php?id=" . $device_db_id);
            exit();
        }
    }

    // Ambil detail perangkat (setelah potensi update)
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?"); 
    $stmt->execute([$device_db_id, $userId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $_SESSION['error_message'] = "Perangkat tidak ditemukan atau Anda tidak memiliki akses.";
        header('Location: list.php');
        exit();
    }

    // Ambil semua jadwal untuk perangkat ini
    $schedules = [];
    $stmtSchedules = $pdo->prepare("SELECT * FROM device_schedules WHERE device_id = ? AND user_id = ? ORDER BY start_time ASC");
    $stmtSchedules->execute([$device_db_id, $userId]);
    $schedules = $stmtSchedules->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Device Detail Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan database: ' . $e->getMessage();
    header('Location: list.php');
    exit();
} catch (Exception $e) {
    error_log("General error in device/detail.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan. Silakan coba lagi.';
    header('Location: list.php');
    exit();
}

// Helper function to convert repeat_days bitmask to array of day names
function getRepeatDaysNames($bitmask) {
    $days = [
        1 => 'Senin', 2 => 'Selasa', 4 => 'Rabu', 8 => 'Kamis',
        16 => 'Jumat', 32 => 'Sabtu', 64 => 'Minggu'
    ];
    $selectedDays = [];
    foreach ($days as $bit => $name) {
        if (($bitmask & $bit) === $bit) {
            $selectedDays[] = $name;
        }
    }
    return empty($selectedDays) ? 'Tidak Berulang' : implode(', ', $selectedDays);
}

// Map for days for checkboxes
$days_map = [
    1 => 'Senin', 2 => 'Selasa', 4 => 'Rabu', 8 => 'Kamis',
    16 => 'Jumat', 32 => 'Sabtu', 64 => 'Minggu'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Perangkat - <?= htmlspecialchars($device['device_name']) ?> - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Minimalis styling untuk halaman detail */
        .device-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            margin-bottom: 24px;
        }
        .device-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .device-header .device-meta {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .device-header .device-actions {
            margin-top: 12px;
        }
        .device-header .device-actions .btn {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 8px;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 16px;
            background: #f8f9fc;
            transition: all 0.2s ease;
            height: 100%; /* Memastikan tinggi card seragam */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card:hover {
            background: #eef2f7;
        }
        .stat-card .stat-icon {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 8px;
        }
        .stat-card .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-badge.online { background: #c6f6d5; color: #22543d; }
        .status-badge.online .dot { background: #38a169; }
        .status-badge.offline { background: #fed7d7; color: #742a2a; }
        .status-badge.offline .dot { background: #e53e3e; }
        .status-badge.active { background: #bee3f8; color: #2a4365; } /* ON */
        .status-badge.active .dot { background: #3182ce; }
        .status-badge.idle { background: #fefcbf; color: #744210; } /* OFF */
        .status-badge.idle .dot { background: #d69e2e; }
        .chart-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            height: 100%;
        }
        .chart-card .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 12px 20px;
        }
        .chart-card .card-body {
            padding: 16px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row .label {
            color: #718096;
        }
        .info-row .value {
            font-weight: 500;
            color: #2d3748;
        }
        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
        }
        .back-link:hover {
            color: #5a67d8;
        }
        @media (max-width: 768px) {
            .device-header {
                padding: 16px;
            }
            .device-header h1 {
                font-size: 1.2rem;
            }
            .stat-card .stat-value {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-bolt"></i> Lantabura
            </div>
            <div class="list-group list-group-flush">
                <a href="../index.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="list.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-microchip"></i> Perangkat Saya
                </a>
                <a href="../monitoring/history.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history"></i> Data Historis
                </a>
                <a href="../analytics/overview.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-pie"></i> Analisis & Laporan
                </a>
                <a href="../settings/notification.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-bell"></i> Notifikasi
                </a>
                <a href="../settings/profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-cog"></i> Pengaturan Akun
                </a>
                <a href="../../auth/logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light">
                <button class="btn toggle-button" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
                    <ul class="navbar-nav">
                        <li class="nav-item me-3">
                            <a class="nav-link" href="#"><i class="fas fa-sync-alt"></i> Sync</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="#">Profil Saya</a>
                                <a class="dropdown-item" href="#">Pengaturan</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="../../auth/logout.php">Logout</a>
                            </div>
                        </li>
                        <li class="nav-item ms-3">
                            <a class="nav-link text-danger" href="../../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Back link -->
                <a href="list.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Perangkat
                </a>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Device Header -->
                <div class="device-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <h1><i class="fas fa-microchip me-2"></i><?= htmlspecialchars($device['device_name']) ?></h1>
                            <div class="device-meta">
                                <span class="me-3"><i class="fas fa-fingerprint me-1"></i>ID: <?= htmlspecialchars($device['device_id']) ?></span>
                                <span class="me-3"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($device['device_type'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($device['location']) ?></span>
                            </div>
                        </div>
                        <div class="device-actions d-flex gap-2">
                            <!-- Tombol Edit dan Hapus Perangkat -->
                            <a href="edit.php?id=<?= htmlspecialchars($device['id']) ?>" class="btn btn-light btn-sm"><i class="fas fa-edit me-1"></i> Edit</a>
                            <a href="delete.php?id=<?= htmlspecialchars($device['id']) ?>" class="btn btn-light btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus perangkat ini? Data historis terkait juga akan dihapus.');"><i class="fas fa-trash-alt me-1"></i> Hapus</a>
                            
                            <!-- Tombol Pengaturan Kontrol Relay -->
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#relaySettingsModal">
                                <i class="fas fa-sliders-h me-1"></i> Setting
                            </button>
                            <!-- Tombol Jadwal -->
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-clock me-1"></i> Jadwal
                            </button>
                        </div>
                    </div>
                </div>

                <h4 class="section-title mt-4">Status & Daya Aktif</h4>
                <div class="row g-3 mb-4">
                    <!-- Machine Status -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                            <div class="stat-value">
                                <span id="realtime-machine-status-display" class="status-badge offline">
                                    <span class="dot"></span> Memuat...
                                </span>
                            </div>
                            <div class="stat-label">Status Mesin</div>
                        </div>
                    </div>
                    <!-- NEW: Relay Control Card -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-power-off"></i></div>
                            <div class="stat-value">
                                <form id="manualRelayControlForm" method="POST" action="detail.php?id=<?= htmlspecialchars($device_db_id) ?>">
                                    <input type="hidden" name="form_type" value="manual_relay_control">
                                    <input type="hidden" name="command" id="relay_command_hidden_input">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" id="relay_toggle_switch" role="switch" style="transform: scale(1.5);"> <!-- Make switch larger -->
                                    </div>
                                </form>
                            </div>
                            <div class="stat-label">Kontrol Relay</div>
                        </div>
                    </div>
                    <!-- Current Power -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                            <div class="stat-value" id="realtime-power">Memuat...</div>
                            <div class="stat-label">Daya Saat Ini</div>
                        </div>
                    </div>
                    <!-- Current Voltage -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-charging-station"></i></div>
                            <div class="stat-value" id="realtime-voltage">Memuat...</div>
                            <div class="stat-label">Tegangan</div>
                        </div>
                    </div>
                    <!-- Current Current -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-plug"></i></div>
                            <div class="stat-value" id="realtime-current">Memuat...</div>
                            <div class="stat-label">Arus</div>
                        </div>
                    </div>
                </div>

                <h4 class="section-title mt-4">Ringkasan Biaya & Konsumsi</h4>
                <div class="row g-3 mb-4">
                    <!-- Daily Energy -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="stat-value" id="daily-energy">Memuat...</div>
                            <div class="stat-label">Energi Hari Ini</div>
                        </div>
                    </div>
                    <!-- Daily Cost -->
                    <div class="6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-money-bill-alt"></i></div>
                            <div class="stat-value" id="daily-cost">Memuat...</div>
                            <div class="stat-label">Biaya Hari Ini</div>
                        </div>
                    </div>
                    <!-- Monthly Energy -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-value" id="monthly-energy">Memuat...</div>
                            <div class="stat-label">Energi Bulan Ini</div>
                        </div>
                    </div>
                    <!-- Monthly Cost -->
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                            <div class="stat-value" id="monthly-cost">Memuat...</div>
                            <div class="stat-label">Biaya Bulan Ini</div>
                        </div>
                    </div>
                </div>

                <h4 class="section-title mt-4">Proyeksi Biaya & Total Akumulatif</h4>
                <div class="row g-3 mb-4">
                    <!-- Weekly Cost Projection -->
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                            <div class="stat-value" id="weekly-cost-projection">Memuat...</div>
                            <div class="stat-label">Proyeksi Biaya Mingguan</div>
                        </div>
                    </div>
                    <!-- Monthly Cost Projection -->
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="stat-value" id="monthly-cost-projection">Memuat...</div>
                            <div class="stat-label">Proyeksi Biaya Bulanan</div>
                        </div>
                    </div>
                    <!-- Cumulative Energy -->
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-infinity"></i></div>
                            <div class="stat-value" id="cumulative-energy">Memuat...</div>
                            <div class="stat-label">Total Energi Akumulatif</div>
                        </div>
                    </div>
                </div>

                <!-- Info & Chart Row -->
                <div class="row g-4 mb-4">
                    <!-- Device Info Sidebar -->
                    <div class="col-lg-4">
                        <div class="card chart-card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2"></i>Informasi Perangkat
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="label">Nama Perangkat</span>
                                    <span class="value"><?= htmlspecialchars($device['device_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">ID Hardware</span>
                                    <span class="value"><?= htmlspecialchars($device['device_id']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Tipe</span>
                                    <span class="value"><?= htmlspecialchars($device['device_type'] ?? 'N/A') ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Lokasi</span>
                                    <span class="value"><?= htmlspecialchars($device['location']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Tarif per kWh</span>
                                    <span class="value">Rp <?= number_format($device['tarif_per_kwh'], 0, ',', '.') ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Status Koneksi</span>
                                    <span class="value">
                                        <span id="device-status-text-info" class="status-badge offline">
                                            <span class="dot"></span> Memuat...
                                        </span>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Terakhir Update Data</span>
                                    <span class="value" id="device-last-connected">Memuat...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="col-lg-8">
                        <div class="card chart-card">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-2"></i>Grafik Daya (24 Jam Terakhir)
                            </div>
                            <div class="card-body">
                                <div class="chart-message-container" id="realtimePowerChartMessage"></div>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="realtimePowerChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->

    <!-- Relay Control Settings Modal -->
    <div class="modal fade" id="relaySettingsModal" tabindex="-1" aria-labelledby="relaySettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="relaySettingsModalLabel">Pengaturan Kontrol Relay Otomatis untuk <?= htmlspecialchars($device['device_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="detail.php?id=<?= htmlspecialchars($device_db_id) ?>">
                        <input type="hidden" name="form_type" value="relay_control_settings">
                        
                        <div class="mb-3">
                            <label for="anomaly_threshold_percent_modal" class="form-label">Ambang Batas Anomali (%)</label>
                            <input type="number" step="0.1" class="form-control" id="anomaly_threshold_percent_modal" name="anomaly_threshold_percent" value="<?= htmlspecialchars($device['anomaly_threshold_percent'] ?? '20.0') ?>">
                            <small class="form-text text-muted">Persentase kenaikan daya rata-rata dari baseline untuk dianggap anomali kritis.</small>
                        </div>

                        <!-- START: Penambahan input spike_threshold_percent -->
                        <div class="mb-3">
                            <label for="spike_threshold_percent_modal" class="form-label">Ambang Batas Spike (%)</label>
                            <input type="number" step="0.1" class="form-control" id="spike_threshold_percent_modal" name="spike_threshold_percent" value="<?= htmlspecialchars($device['spike_threshold_percent'] ?? '180.0') ?>">
                            <small class="form-text text-muted">Persentase lonjakan daya puncak (max_power) dari baseline untuk dianggap spike kritis. Default: 180%.</small>
                        </div>
                        <!-- END: Penambahan input spike_threshold_percent -->

                        <hr class="my-4">

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto_shutdown_standby_modal" name="auto_shutdown_standby" <?= ($device['auto_shutdown_standby'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_shutdown_standby_modal">Aktifkan Pemutusan Standby Otomatis</label>
                            <small class="form-text text-muted">Mematikan perangkat jika terdeteksi konsumsi daya rendah terus-menerus.</small>
                        </div>
                        <div class="mb-3">
                            <label for="standby_threshold_watt_modal" class="form-label">Ambang Batas Daya Standby (Watt)</label>
                            <input type="number" step="0.1" class="form-control" id="standby_threshold_watt_modal" name="standby_threshold_watt" value="<?= htmlspecialchars($device['standby_threshold_watt'] ?? '5.0') ?>">
                            <small class="form-text text-muted">Perangkat akan dimatikan jika konsumsi daya rata-rata di bawah nilai ini selama durasi tertentu.</small>
                        </div>
                        <div class="mb-3">
                            <label for="standby_detection_duration_minutes_modal" class="form-label">Durasi Deteksi Standby (Menit)</label>
                            <input type="number" step="1" class="form-control" id="standby_detection_duration_minutes_modal" name="standby_detection_duration_minutes" value="<?= htmlspecialchars($device['standby_detection_duration_minutes'] ?? '10') ?>">
                            <small class="form-text text-muted">Lama waktu konsumsi daya rendah terdeteksi sebelum perangkat dimatikan.</small>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto_shutdown_overload_modal" name="auto_shutdown_overload" <?= ($device['auto_shutdown_overload'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_shutdown_overload_modal">Aktifkan Pemutusan Overload Otomatis</label>
                            <small class="form-text text-muted">Jika anomali daya kritis terdeteksi, perangkat akan dimatikan otomatis (dengan konfirmasi 3x dalam 10 menit).</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Pengaturan Kontrol</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">Pengaturan Jadwal untuk <?= htmlspecialchars($device['device_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Existing Schedules Section -->
                    <h6>Jadwal Aktif</h6>
                    <?php if (empty($schedules)): ?>
                        <div class="alert alert-info" role="alert">Belum ada jadwal yang ditambahkan untuk perangkat ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Waktu Mulai</th>
                                        <th>Waktu Berakhir</th>
                                        <th>Aksi</th>
                                        <th>Hari</th>
                                        <th>Aktif</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($schedule['schedule_name']) ?></td>
                                            <td><?= htmlspecialchars($schedule['start_time']) ?></td>
                                            <td><?= htmlspecialchars($schedule['end_time']) ?></td>
                                            <td><span class="badge <?= $schedule['action'] === 'on' ? 'bg-success' : 'bg-danger' ?>"><?= strtoupper(htmlspecialchars($schedule['action'])) ?></span></td>
                                            <td><?= getRepeatDaysNames($schedule['repeat_days']) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="form_type" value="schedule_management">
                                                    <input type="hidden" name="schedule_action" value="toggle_active">
                                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="is_active_schedule" value="1" <?= ($schedule['is_active'] ? 'checked' : '') ?> onchange="this.form.submit()">
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editScheduleModal" 
                                                        data-schedule-id="<?= $schedule['id'] ?>" 
                                                        data-schedule-name="<?= htmlspecialchars($schedule['schedule_name']) ?>" 
                                                        data-start-time="<?= htmlspecialchars($schedule['start_time']) ?>" 
                                                        data-end-time="<?= htmlspecialchars($schedule['end_time']) ?>" 
                                                        data-action="<?= htmlspecialchars($schedule['action']) ?>" 
                                                        data-repeat-days="<?= $schedule['repeat_days'] ?>" 
                                                        data-is-active="<?= $schedule['is_active'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');">
                                                    <input type="hidden" name="form_type" value="schedule_management">
                                                    <input type="hidden" name="schedule_action" value="delete">
                                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <!-- Add New Schedule Form -->
                    <h6>Tambah Jadwal Baru</h6>
                    <form method="POST">
                        <input type="hidden" name="form_type" value="schedule_management">
                        <input type="hidden" name="schedule_action" value="add">
                        <div class="mb-3">
                            <label for="new_schedule_name" class="form-label">Nama Jadwal</label>
                            <input type="text" class="form-control" id="new_schedule_name" name="schedule_name" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_start_time" class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control" id="new_start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="new_end_time" class="form-label">Waktu Berakhir</label>
                                <input type="time" class="form-control" id="new_end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aksi</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="action" id="new_action_on" value="on" checked>
                                <label class="form-check-label" for="new_action_on">ON</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="action" id="new_action_off" value="off">
                                <label class="form-check-label" for="new_action_off">OFF</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ulangi Hari</label><br>
                            <?php foreach ($days_map as $bit => $day_name): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="repeat_days[]" id="new_repeat_day_<?= $bit ?>" value="<?= $bit ?>">
                                    <label class="form-check-label" for="new_repeat_day_<?= $bit ?>"><?= $day_name ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active_schedule" id="new_is_active_schedule" value="1" checked>
                            <label class="form-check-label" for="new_is_active_schedule">Aktifkan Jadwal Ini</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Tambah Jadwal</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal (Hidden by default, populated by JS) -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">Edit Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editScheduleForm">
                        <input type="hidden" name="form_type" value="schedule_management">
                        <input type="hidden" name="schedule_action" value="edit">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id">
                        <div class="mb-3">
                            <label for="edit_schedule_name" class="form-label">Nama Jadwal</label>
                            <input type="text" class="form-control" id="edit_schedule_name" name="schedule_name" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_start_time" class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_end_time" class="form-label">Waktu Berakhir</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aksi</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="action" id="edit_action_on" value="on">
                                <label class="form-check-label" for="edit_action_on">ON</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="action" id="edit_action_off" value="off">
                                <label class="form-check-label" for="edit_action_off">OFF</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ulangi Hari</label><br>
                            <?php foreach ($days_map as $bit => $day_name): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="repeat_days[]" id="edit_repeat_day_<?= $bit ?>" value="<?= $bit ?>">
                                    <label class="form-check-label" for="edit_repeat_day_<?= $bit ?>"><?= $day_name ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active_schedule" id="edit_is_active_schedule" value="1">
                            <label class="form-check-label" for="edit_is_active_schedule">Aktifkan Jadwal Ini</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Perubahan Jadwal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const DEVICE_DB_ID = <?= json_encode($device['id']) ?>;
        const USER_ID = <?= json_encode($userId) ?>;
        const API_BASE_URL = '../../api/'; // Sesuaikan jika path API berbeda

        // Toggle sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });

        // --- Chart.js Initialization ---
        let realtimePowerChartInstance; // Deklarasikan sebagai variabel global

        function createChart(canvasId, labels, dataPoints, labelText, backgroundColor, borderColor, unit) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            const ctx = canvas.getContext('2d');
            
            // Hancurkan grafik yang ada jika ada
            if (realtimePowerChartInstance) {
                realtimePowerChartInstance.destroy();
            }
            
            realtimePowerChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: labelText,
                        lineTension: 0.4, // Membuat garis lebih smooth
                        backgroundColor: backgroundColor,
                        borderColor: borderColor,
                        pointRadius: 2, // Ukuran titik lebih kecil
                        pointBackgroundColor: borderColor,
                        pointBorderColor: "rgba(255,255,255,0.8)",
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: borderColor,
                        pointHitRadius: 10,
                        pointBorderWidth: 1,
                        data: dataPoints,
                        fill: 'origin',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleColor: '#6e707e',
                            titleFont: { weight: 'bold' },
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2).replace('.', ',') + unit;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: { maxTicksLimit: 10, font: { size: 10 } }
                        },
                        y: {
                            beginAtZero: true, // Pastikan sumbu Y mulai dari 0
                            ticks: {
                                maxTicksLimit: 5,
                                padding: 10,
                                font: { size: 10 },
                                callback: function(value, index, values) {
                                    return value + unit;
                                }
                            },
                            grid: { color: "rgba(0, 0, 0, .05)" }
                        },
                    }
                }
            });
            return realtimePowerChartInstance;
        }

        // Fungsi untuk memuat data grafik daya real-time
        function loadRealtimePowerChartForDevice() {
            if (!DEVICE_DB_ID) return;

            const chartCanvas = document.getElementById('realtimePowerChart');
            const chartMessageContainer = document.getElementById('realtimePowerChartMessage');

            if (chartMessageContainer) chartMessageContainer.innerHTML = '';
            if (chartCanvas) chartCanvas.style.display = 'block';

            fetch(`${API_BASE_URL}get_device_chart_data.php?device_id=${DEVICE_DB_ID}&chart_type=realtime_power`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response for chart was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.labels && data.labels.length > 0) {
                        createChart(
                            'realtimePowerChart',
                            data.labels,
                            data.data,
                            "Daya (W)",
                            "rgba(67, 97, 238, 0.2)",
                            "rgba(67, 97, 238, 1)",
                            " W"
                        );
                    } else {
                        if (chartCanvas) chartCanvas.style.display = 'none';
                        if (chartMessageContainer) chartMessageContainer.innerHTML = `<div class="alert alert-info text-center py-2 mb-0" role="alert">${data.message || 'Belum ada data daya untuk 24 jam terakhir.'}</div>`;
                        if (realtimePowerChartInstance) {
                            realtimePowerChartInstance.destroy();
                            realtimePowerChartInstance = null;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading realtime power chart data:', error);
                    if (chartCanvas) chartCanvas.style.display = 'none';
                    if (chartMessageContainer) chartMessageContainer.innerHTML = `<div class="alert alert-danger text-center py-2 mb-0" role="alert">Gagal memuat data grafik: ${error.message}</div>`;
                    if (realtimePowerChartInstance) {
                        realtimePowerChartInstance.destroy();
                        realtimePowerChartInstance = null;
                    }
                });
        }

        // Fungsi untuk memperbarui status toggle relay
        function updateRelayToggleState(relayState) {
            const relayToggleSwitch = document.getElementById('relay_toggle_switch');
            if (relayToggleSwitch) {
                // Pastikan switch diaktifkan sebelum memperbarui statusnya dari server
                relayToggleSwitch.disabled = false;
                relayToggleSwitch.checked = (relayState === 'on');
            }
        }

        // Fungsi untuk memperbarui semua data real-time dan akumulatif
        function updateRealtimeDataForDevice() {
            if (!DEVICE_DB_ID || !USER_ID) {
                console.error("Device ID atau User ID tidak ditemukan.");
                resetRealtimeDisplay();
                return;
            }

            fetch(`${API_BASE_URL}get_latest_data.php?device_id=${DEVICE_DB_ID}&user_id=${USER_ID}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response for latest data was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.latest_data) {
                        const latest = data.latest_data;
                        
                        // Update status koneksi perangkat (badge di info sidebar)
                        const deviceStatusInfoEl = document.getElementById('device-status-text-info');
                        if (deviceStatusInfoEl) {
                            const deviceStatusText = latest.device_status || 'offline';
                            deviceStatusInfoEl.innerHTML = `<span class="dot"></span> ${deviceStatusText}`;
                            deviceStatusInfoEl.className = 'status-badge'; // Reset class
                            if (deviceStatusText === 'online') {
                                deviceStatusInfoEl.classList.add('online');
                            } else {
                                deviceStatusInfoEl.classList.add('offline');
                            }
                        }

                        // Update stat cards dan status mesin
                        if (latest.device_status === 'online') {
                            document.getElementById('realtime-power').innerText = `${parseFloat(latest.power || 0).toFixed(2).replace('.', ',')} W`;
                            document.getElementById('realtime-voltage').innerText = `${parseFloat(latest.voltage || 0).toFixed(2).replace('.', ',')} V`;
                            document.getElementById('realtime-current').innerText = `${parseFloat(latest.current || 0).toFixed(2).replace('.', ',')} A`;
                            
                            // Update machine status badge
                            const machineStatusDisplayEl = document.getElementById('realtime-machine-status-display');
                            if (machineStatusDisplayEl) {
                                const machineStatusText = latest.machine_status || 'UNKNOWN'; // Default UNKNOWN jika tidak ada
                                machineStatusDisplayEl.innerHTML = `<span class="dot"></span> ${machineStatusText}`;
                                machineStatusDisplayEl.className = 'status-badge'; // Reset class
                                if (machineStatusText === 'ON') {
                                    machineStatusDisplayEl.classList.add('active');
                                } else if (machineStatusText === 'OFF') {
                                    machineStatusDisplayEl.classList.add('idle');
                                } else { // UNKNOWN atau status lain saat online
                                    machineStatusDisplayEl.classList.add('online'); // Bisa juga pakai 'active' atau 'idle' tergantung definisi
                                }
                            }

                            // NEW: Update relay toggle switch based on server state
                            updateRelayToggleState(latest.relay_state || 'off');

                        } else {
                            // Perangkat OFFLINE, tampilkan "0" untuk daya, tegangan, arus
                            document.getElementById('realtime-power').innerText = '0,00 W';
                            document.getElementById('realtime-voltage').innerText = '0,00 V';
                            document.getElementById('realtime-current').innerText = '0,00 A';

                            // Status mesin juga harus OFFLINE jika perangkat offline
                            const machineStatusDisplayEl = document.getElementById('realtime-machine-status-display');
                            if (machineStatusDisplayEl) {
                                machineStatusDisplayEl.innerHTML = '<span class="dot"></span> OFFLINE';
                                machineStatusDisplayEl.className = 'status-badge offline';
                            }

                            // NEW: Relay toggle switch juga harus diatur ke OFF dan dinonaktifkan jika perangkat offline
                            const relayToggleSwitch = document.getElementById('relay_toggle_switch');
                            if (relayToggleSwitch) {
                                relayToggleSwitch.checked = false;
                                relayToggleSwitch.disabled = true;
                            }
                        }

                        // Update data akumulatif (ini akan selalu ditampilkan terlepas dari status online/offline)
                        if (document.getElementById('daily-energy')) document.getElementById('daily-energy').innerText = `${parseFloat(latest.daily_energy_consumption || 0).toFixed(3).replace('.', ',')} kWh`;
                        if (document.getElementById('daily-cost')) document.getElementById('daily-cost').innerText = `Rp ${parseFloat(latest.daily_cost_consumption || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
                        if (document.getElementById('monthly-energy')) document.getElementById('monthly-energy').innerText = `${parseFloat(latest.monthly_energy_consumption || 0).toFixed(3).replace('.', ',')} kWh`;
                        if (document.getElementById('monthly-cost')) document.getElementById('monthly-cost').innerText = `Rp ${parseFloat(latest.monthly_cost_consumption || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
                        
                        // Perbarui kartu proyeksi baru
                        if (document.getElementById('weekly-cost-projection')) document.getElementById('weekly-cost-projection').innerText = `Rp ${parseFloat(latest.weekly_cost_projection || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
                        if (document.getElementById('monthly-cost-projection')) document.getElementById('monthly-cost-projection').innerText = `Rp ${parseFloat(latest.monthly_cost_projection || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
                        if (document.getElementById('cumulative-energy')) document.getElementById('cumulative-energy').innerText = `${parseFloat(latest.cumulative_energy || 0).toFixed(3).replace('.', ',')} kWh`;


                        // Update last connected timestamp
                        const lastConnectedEl = document.getElementById('device-last-connected');
                        if (lastConnectedEl) {
                            lastConnectedEl.innerText = latest.created_at || 'Belum ada data';
                        }

                    } else {
                        console.error('Failed to fetch latest data:', data.message);
                        resetRealtimeDisplay();
                    }
                })
                .catch(error => {
                    console.error('Error fetching latest data:', error);
                    resetRealtimeDisplay();
                });
        }

        // Fungsi untuk mereset tampilan data real-time ke default (0 atau OFFLINE)
        function resetRealtimeDisplay() {
            document.getElementById('realtime-power').innerText = '0,00 W';
            document.getElementById('realtime-voltage').innerText = '0,00 V';
            document.getElementById('realtime-current').innerText = '0,00 A';
            
            const machineStatusDisplayEl = document.getElementById('realtime-machine-status-display');
            if (machineStatusDisplayEl) {
                machineStatusDisplayEl.innerHTML = '<span class="dot"></span> OFFLINE';
                machineStatusDisplayEl.className = 'status-badge offline';
            }

            // NEW: Reset relay toggle switch ke off dan nonaktifkan saat offline
            const relayToggleSwitch = document.getElementById('relay_toggle_switch');
            if (relayToggleSwitch) {
                relayToggleSwitch.checked = false;
                relayToggleSwitch.disabled = true;
            }

            // Reset status koneksi di info sidebar
            const deviceStatusInfoEl = document.getElementById('device-status-text-info');
            if (deviceStatusInfoEl) {
                deviceStatusInfoEl.innerHTML = '<span class="dot"></span> offline';
                deviceStatusInfoEl.className = 'status-badge offline';
            }

            document.getElementById('device-last-connected').innerText = 'Tidak ada data terbaru';
            if (document.getElementById('daily-energy')) document.getElementById('daily-energy').innerText = '0,000 kWh';
            if (document.getElementById('daily-cost')) document.getElementById('daily-cost').innerText = 'Rp 0';
            if (document.getElementById('monthly-energy')) document.getElementById('monthly-energy').innerText = '0,000 kWh';
            if (document.getElementById('monthly-cost')) document.getElementById('monthly-cost').innerText = 'Rp 0';
            // Reset kartu proyeksi baru
            if (document.getElementById('weekly-cost-projection')) document.getElementById('weekly-cost-projection').innerText = 'Rp 0';
            if (document.getElementById('monthly-cost-projection')) document.getElementById('monthly-cost-projection').innerText = 'Rp 0';
            if (document.getElementById('cumulative-energy')) document.getElementById('cumulative-energy').innerText = '0,000 kWh';
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (DEVICE_DB_ID && USER_ID) {
                // Panggil fungsi update pertama kali
                updateRealtimeDataForDevice();
                loadRealtimePowerChartForDevice();
            } else {
                console.error("Device ID atau User ID tidak ditemukan.");
                resetRealtimeDisplay();
            }

            // Atur interval untuk update berkala
            setInterval(updateRealtimeDataForDevice, 5000); // Setiap 5 detik untuk data real-time
            setInterval(loadRealtimePowerChartForDevice, 15000); // Setiap 15 detik untuk grafik

            // --- JavaScript untuk Toggle Relay Manual ---
            const relayToggleSwitch = document.getElementById('relay_toggle_switch');
            if (relayToggleSwitch) {
                relayToggleSwitch.addEventListener('change', function() {
                    const desiredState = this.checked ? 'on' : 'off';
                    const confirmMessage = `Apakah Anda yakin ingin ${desiredState === 'on' ? 'menyalakan' : 'mematikan'} relay?`;

                    if (confirm(confirmMessage)) {
                        // Nonaktifkan switch sementara untuk mencegah klik ganda
                        this.disabled = true;

                        // Set nilai input tersembunyi dan submit form
                        document.getElementById('relay_command_hidden_input').value = desiredState;
                        document.getElementById('manualRelayControlForm').submit();
                    } else {
                        // Jika pengguna membatalkan, kembalikan switch ke status sebelumnya
                        this.checked = !this.checked;
                    }
                });
            }

            // --- JavaScript untuk Modal Edit Jadwal ---
            const editScheduleModalElement = document.getElementById('editScheduleModal');
            editScheduleModalElement.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                const button = event.relatedTarget; 
                // Extract info from data-bs-* attributes
                const scheduleId = button.getAttribute('data-schedule-id');
                const scheduleName = button.getAttribute('data-schedule-name');
                const startTime = button.getAttribute('data-start-time');
                const endTime = button.getAttribute('data-end-time');
                const action = button.getAttribute('data-action');
                const repeatDays = parseInt(button.getAttribute('data-repeat-days')); // Bitmask
                const isActive = parseInt(button.getAttribute('data-is-active'));

                // Update the modal's content.
                const modalTitle = editScheduleModalElement.querySelector('.modal-title');
                const form = editScheduleModalElement.querySelector('#editScheduleForm');

                modalTitle.textContent = `Edit Jadwal: ${scheduleName}`;
                form.querySelector('#edit_schedule_id').value = scheduleId;
                form.querySelector('#edit_schedule_name').value = scheduleName;
                form.querySelector('#edit_start_time').value = startTime;
                form.querySelector('#edit_end_time').value = endTime;

                // Set action radio buttons
                if (action === 'on') {
                    form.querySelector('#edit_action_on').checked = true;
                } else {
                    form.querySelector('#edit_action_off').checked = true;
                }

                // Set repeat days checkboxes
                const dayCheckboxes = form.querySelectorAll('input[name="repeat_days[]"]');
                dayCheckboxes.forEach(checkbox => {
                    const dayBit = parseInt(checkbox.value);
                    checkbox.checked = (repeatDays & dayBit) === dayBit;
                });

                // Set is_active switch
                form.querySelector('#edit_is_active_schedule').checked = (isActive === 1);
            });

            // --- Logika untuk membuka modal jadwal secara otomatis setelah POST redirect ---
            <?php if (isset($_SESSION['open_schedule_modal']) && $_SESSION['open_schedule_modal']): ?>
                const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                scheduleModal.show();
                <?php unset($_SESSION['open_schedule_modal']); ?>
            <?php endif; ?>

            // --- Logika untuk membuka modal pengaturan relay secara otomatis setelah POST redirect ---
            <?php if (isset($_SESSION['open_relay_settings_modal']) && $_SESSION['open_relay_settings_modal']): ?>
                const relaySettingsModal = new bootstrap.Modal(document.getElementById('relaySettingsModal'));
                relaySettingsModal.show();
                <?php unset($_SESSION['open_relay_settings_modal']); ?>
            <?php endif; ?>
        });
    </script>

</body>
</html>
