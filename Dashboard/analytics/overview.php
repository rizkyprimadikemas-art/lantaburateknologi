<?php
// File: dashboard/analytics/overview.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$userName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Pengguna';
$userEmail = $currentUser['email'] ?? 'email@example.com';

$allDevices = [];
$selectedDevice = null;
$selectedDeviceDbId = $_GET['device_id'] ?? null; // ID perangkat yang dipilih dari URL

try {
    $pdo = getPDOConnection();

    // Ambil semua perangkat milik pengguna
    $stmt = $pdo->prepare("SELECT id, device_name, device_id FROM devices WHERE user_id = ? AND is_active = 1 ORDER BY device_name ASC");
    $stmt->execute([$userId]);
    $allDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Validasi selectedDeviceDbId
    $validDeviceSelected = false;
    if ($selectedDeviceDbId && $selectedDeviceDbId !== 'all') {
        foreach ($allDevices as $device) {
            if ($device['id'] == $selectedDeviceDbId) {
                $validDeviceSelected = true;
                $selectedDevice = $device;
                break;
            }
        }
    }

    if (!$validDeviceSelected || $selectedDeviceDbId === 'all') {
        $selectedDeviceDbId = 'all'; // Default ke 'all' jika tidak valid atau 'all' di URL
    }

} catch (PDOException $e) {
    error_log("Error fetching devices for analytics: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan database saat memuat daftar perangkat.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis & Laporan - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr CSS for Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Utility class untuk menyembunyikan elemen */
        .hidden {
            display: none !important;
        }

        /* Menggunakan warna yang sudah ada di kode Anda atau default Bootstrap */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fc; /* Warna background yang sudah ada */
        }

        /* General Card Styling */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            background-color: #ffffff; /* Putih untuk kartu */
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid #e2e8f0; /* Warna border yang sudah ada */
            font-weight: 600;
            font-size: 0.9rem;
            padding: 15px 20px;
            color: #2d3748; /* Warna teks yang sudah ada */
            display: flex;
            align-items: center;
        }
        .card-header i {
            margin-right: 8px;
            color: #667eea; /* Menggunakan warna dari gradient device-select-card */
        }
        .card-body {
            padding: 20px;
        }

        /* Summary Cards */
        .summary-card {
            padding: 20px;
            background: #f8f9fc; /* Menggunakan warna background yang sudah ada */
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .summary-card .icon-bg {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2.5rem;
            color: rgba(102, 126, 234, 0.1); /* Light primary color dari gradient device-select-card */
            z-index: 0;
        }
        .summary-card .content {
            position: relative;
            z-index: 1;
        }
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748; /* Warna teks yang sudah ada */
            margin-bottom: 5px;
        }
        .summary-card .label {
            font-size: 0.85rem;
            color: #a0aec0; /* Warna teks yang sudah ada */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .summary-card .change {
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0; /* Warna border yang sudah ada */
        }
        .summary-card .change.positive { color: #38a169; } /* Warna sudah ada */
        .summary-card .change.negative { color: #e53e3e; } /* Warna sudah ada */
        .summary-card .change.neutral { color: #718096; } /* Warna sudah ada */

        /* Chart Containers */
        .chart-container {
            position: relative;
            height: 300px; /* Tinggi standar untuk chart */
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chart-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 12px;
            transition: opacity 0.3s ease;
        }
        .chart-loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .chart-loading-overlay .spinner-border {
            width: 2.5rem;
            height: 2.5rem;
            color: #667eea; /* Menggunakan warna dari gradient device-select-card */
        }
        .chart-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .chart-controls .btn {
            flex-grow: 1;
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 8px 12px;
            border-color: #667eea; /* Menggunakan warna dari gradient device-select-card */
            color: #667eea; /* Menggunakan warna dari gradient device-select-card */
        }
        .chart-controls .btn.active, .chart-controls .btn:hover {
            background-color: #667eea; /* Menggunakan warna dari gradient device-select-card */
            color: white;
            border-color: #667eea; /* Menggunakan warna dari gradient device-select-card */
        }
        .chart-controls .btn:focus {
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25); /* Menggunakan warna dari gradient device-select-card */
        }

        /* Device Selector Card */
        .device-select-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* Warna gradient yang sudah ada */
            color: white;
        }
        .device-select-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .device-select-card .card-header i {
            color: #4fd1c5; /* Warna teal dari contoh sebelumnya, sebagai aksen */
        }
        .device-select-card select {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 500;
            color: white;
            appearance: none; /* Remove default arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        .device-select-card select option {
            background-color: #764ba2; /* Background untuk opsi, dari gradient */
            color: white;
        }
        .device-select-card select:focus {
            border-color: #4fd1c5; /* Warna teal sebagai aksen */
            box-shadow: 0 0 0 0.25rem rgba(79, 209, 197, 0.25);
        }

        /* Date Range Selector Styling */
        .date-range-card .flatpickr-input {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 500;
            color: #2d3748;
            width: 100%;
        }
        .date-range-card .flatpickr-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        /* New: Date Range Presets */
        .date-range-presets .btn {
            border-radius: 8px;
            font-size: 0.8rem;
            padding: 6px 10px;
            margin-bottom: 5px;
        }

        /* Daily Detail Table */
        .daily-detail-table {
            margin-top: 15px;
            border-collapse: separate;
            border-spacing: 0 8px; /* Spasi antar baris */
        }
        .daily-detail-table thead th {
            border-bottom: 1px solid #e2e8f0; /* Warna border yang sudah ada */
            padding-bottom: 10px;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #a0aec0; /* Warna teks yang sudah ada */
            background-color: #ffffff; /* Match card background */
            position: sticky;
            top: 0;
            z-index: 2;
            cursor: pointer; /* Menandakan bisa diurutkan */
        }
        .daily-detail-table thead th.sortable:hover {
            color: #667eea;
        }
        .daily-detail-table thead th.sortable.asc::after {
            content: " \25B2"; /* Panah atas */
            font-size: 0.7em;
        }
        .daily-detail-table thead th.sortable.desc::after {
            content: " \25BC"; /* Panah bawah */
            font-size: 0.7em;
        }

        .daily-detail-table tbody tr {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }
        .daily-detail-table tbody tr:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        .daily-detail-table td {
            font-size: 0.875rem;
            vertical-align: middle;
            padding: 12px 15px;
            border: none; /* Hilangkan border sel */
        }
        /* Rounded corners for first and last cells in a row */
        .daily-detail-table tbody tr td:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        .daily-detail-table tbody tr td:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }


        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-badge.online, .status-badge.active { background: #c6f6d5; color: #22543d; } /* Warna sudah ada */
        .status-badge.online .dot, .status-badge.active .dot { background: #28a745; } /* Warna sudah ada */
        .status-badge.offline, .status-badge.idle { background: #fed7d7; color: #742a2a; } /* Warna sudah ada */
        .status-badge.offline .dot, .status-badge.idle .dot { background: #dc3545; } /* Warna sudah ada */
        .status-badge.warning { background: #fff3cd; color: #856404; } /* Warna mirip Bootstrap warning */
        .status-badge.warning .dot { background: #ffc107; } /* Warna mirip Bootstrap warning */

        /* Alert Item Styling (for navbar dropdown) */
        .alert-item {
            border-bottom: 1px solid #e2e8f0; /* Warna border yang sudah ada */
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #2d3748; /* Warna teks yang sudah ada */
            transition: background-color 0.2s ease;
        }
        .alert-item:hover {
            background-color: #f8f9fc; /* Warna background yang sudah ada */
        }
        .alert-item:last-child {
            border-bottom: none;
        }
        .alert-item .icon-circle {
            min-width: 38px; /* Gunakan min-width agar tidak mengecil */
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-right: 12px;
        }
        .alert-item.info .icon-circle { background-color: #0d6efd; } /* Warna sudah ada */
        .alert-item.warning .icon-circle { background-color: #ffc107; } /* Warna sudah ada */
        .alert-item.critical .icon-circle { background-color: #dc3545; } /* Warna sudah ada */
        .alert-item.success .icon-circle { background-color: #28a745; } /* Warna hijau dari online */

        .alert-item.unread {
            background-color: #e0f2f7; /* Warna sudah ada */
            border-left: 4px solid #0d6efd; /* Warna sudah ada */
            padding-left: 16px;
        }
        .alert-item.unread:hover {
            background-color: #d0efff;
        }
        .alert-item .alert-message {
            flex-grow: 1;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .alert-item .alert-message .font-weight-bold {
            font-weight: 600;
            color: #2d3748; /* Warna teks yang sudah ada */
        }
        .alert-item .alert-time {
            font-size: 0.7rem;
            color: #a0aec0; /* Warna teks yang sudah ada */
            white-space: nowrap;
            margin-left: 10px;
        }

        /* Insights & Recommendations Specific Styling */
        .insight-card .list-group-item {
            border: none;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }
        .insight-card .list-group-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .insight-card .list-group-item .icon-circle {
            min-width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-right: 15px;
            background-color: #667eea; /* Primary color */
            color: white;
        }
        .insight-card .list-group-item h6 {
            color: #2d3748;
        }
        .insight-card .list-group-item p {
            color: #718096;
            font-size: 0.85rem;
        }
        .insight-card .list-group-item .badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 15px;
            background-color: #38a169; /* Success color */
        }
        .insight-card .list-group-item .action-button {
            margin-left: auto; /* Dorong tombol ke kanan */
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 8px;
        }


        /* Skeleton Loading Styles */
        .skeleton {
            animation: pulse 1.5s infinite ease-in-out;
            background: linear-gradient(-90deg, #f0f0f0 0%, #f8f8f8 50%, #f0f0f0 100%);
            background-size: 200% 100%;
            border-radius: 4px;
            display: inline-block;
            height: 1em; /* Default height */
            width: 100%; /* Default width */
            opacity: 0; /* Hidden by default */
            transition: opacity 0.3s ease;
        }
        .skeleton-wrapper.loading .skeleton {
            opacity: 1; /* Show skeleton when loading */
        }

        /* Hide actual content when skeleton is active */
        .skeleton-wrapper.loading #avg-power-value,
        .skeleton-wrapper.loading #total-energy-value,
        .skeleton-wrapper.loading #total-cost-value,
        .skeleton-wrapper.loading #avg-uptime-value,
        .skeleton-wrapper.loading .summary-card .label, /* Target label text in summary cards */
        .skeleton-wrapper.loading #avg-power-change,
        .skeleton-wrapper.loading #total-energy-change,
        .skeleton-wrapper.loading #total-cost-change,
        .skeleton-wrapper.loading #avg-uptime-change {
            opacity: 0; /* Hide actual values */
            pointer-events: none;
        }
        /* Insights card specific hiding */
        .skeleton-wrapper.loading #overall-savings-insight h4,
        .skeleton-wrapper.loading #overall-savings-insight p,
        .skeleton-wrapper.loading #recommendations-list .list-group-item h6,
        .skeleton-wrapper.loading #recommendations-list .list-group-item p,
        .skeleton-wrapper.loading .card-body > h5 { /* The "h5.fw-bold mb-3" for recommendations header */
            opacity: 0;
            pointer-events: none;
        }
        /* Ensure the icon-circle in insights is also hidden if it's not a skeleton */
        .skeleton-wrapper.loading .insight-card .list-group-item .icon-circle:not(.skeleton) {
            opacity: 0;
        }


        /* Specific skeleton sizes */
        .skeleton.text { height: 1em; margin-bottom: 0.5em; }
        .skeleton.text.short { width: 50%; }
        .skeleton.text.medium { width: 75%; }
        .skeleton.text.long { width: 100%; }
        .skeleton.value {
            height: 1.8rem;
            width: 80%;
            margin-bottom: 5px;
        }
        .skeleton.label {
            height: 0.85rem;
            width: 60%;
        }
        .skeleton.change {
            height: 0.9rem;
            width: 40%;
            margin-top: 10px;
            padding-top: 10px;
        }
        .skeleton.icon-circle {
            height: 40px;
            width: 40px;
            border-radius: 50%;
        }

        @keyframes pulse {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chart-controls .btn {
                flex-grow: 1;
                width: 100%;
            }
            .date-range-presets {
                margin-top: 10px;
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
                <a href="../devices/list.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-microchip"></i> Perangkat Saya
                </a>
                <a href="../monitoring/history.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history"></i> Data Historis
                </a>
                <a href="overview.php" class="list-group-item list-group-item-action active">
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

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light">
                <button class="btn toggle-button" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
                    <ul class="navbar-nav">
                        <li class="nav-item me-3">
                            <a class="nav-link" href="#" id="sync-button"><i class="fas fa-sync-alt"></i> Sync</a>
                        </li>
                        <!-- START: Navbar Alerts Dropdown -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="unread-alerts-count">
                                    0
                                    <span class="visually-hidden">unread messages</span>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="alertsDropdown" style="min-width: 300px;">
                                <h6 class="dropdown-header">Pusat Peringatan</h6>
                                <div id="recent-alerts-list">
                                    <!-- Alerts akan dimuat di sini oleh JavaScript -->
                                    <a class="dropdown-item text-center small text-gray-500 py-2">Memuat peringatan...</a>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500 py-2" href="../settings/notification.php">Lihat Semua Peringatan</a>
                            </div>
                        </li>
                        <!-- END: Navbar Alerts Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="../settings/profile.php">Profil Saya</a>
                                <a class="dropdown-item" href="#">Pengaturan</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="../../auth/logout.php">Logout</a>
                            </div>
                        </li>
                        <li class="nav-item ms-3">
                            <a class="nav-link text-danger" href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <h1 class="mt-4 mb-4 fw-bold">
                    <i class="fas fa-chart-line me-2 text-primary"></i>Analisis & Laporan
                </h1>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <!-- Device Selector and Date Range Selector -->
                <div class="row mb-4">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <div class="card device-select-card h-100">
                            <div class="card-header">
                                <i class="fas fa-microchip me-2"></i>Pilih Perangkat untuk Analisis
                            </div>
                            <div class="card-body d-flex align-items-center">
                                <?php if (!empty($allDevices)): ?>
                                    <select class="form-select form-select-lg" id="device-selector">
                                        <option value="all" <?= ($selectedDeviceDbId === 'all') ? 'selected' : '' ?>>Semua Perangkat</option>
                                        <?php foreach ($allDevices as $deviceOption): ?>
                                            <option value="<?= $deviceOption['id'] ?>" <?= ($deviceOption['id'] == $selectedDeviceDbId) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($deviceOption['device_name']) ?> (<?= htmlspecialchars($deviceOption['device_id']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0 text-dark w-100">
                                        Belum ada perangkat aktif. <a href="../devices/add.php" class="alert-link text-decoration-underline text-dark">Tambahkan sekarang</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card date-range-card h-100">
                            <div class="card-header">
                                <i class="fas fa-calendar-alt me-2"></i>Pilih Rentang Tanggal
                            </div>
                            <div class="card-body">
                                <!-- Date Range Presets -->
                                <div class="d-flex flex-wrap justify-content-between date-range-presets mb-3">
                                    <button class="btn btn-outline-secondary btn-sm" data-preset="today">Hari Ini</button>
                                    <button class="btn btn-outline-secondary btn-sm" data-preset="yesterday">Kemarin</button>
                                    <button class="btn btn-outline-primary btn-sm active" data-preset="last7days">7 Hari Terakhir</button>
                                    <button class="btn btn-outline-secondary btn-sm" data-preset="thismonth">Bulan Ini</button>
                                    <button class="btn btn-outline-secondary btn-sm" data-preset="lastmonth">Bulan Lalu</button>
                                    <button class="btn btn-outline-secondary btn-sm" data-preset="thisyear">Tahun Ini</button>
                                </div>
                                <input type="text" id="date-range-picker" class="form-control" placeholder="Pilih rentang tanggal...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card skeleton-wrapper" id="summary-card-power">
                            <div class="icon-bg"><i class="fas fa-bolt"></i></div>
                            <div class="content">
                                <div class="value" id="avg-power-value"></div>
                                <div class="label">Rata-rata Daya</div>
                            </div>
                            <div class="change neutral" id="avg-power-change"></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card skeleton-wrapper" id="summary-card-energy">
                            <div class="icon-bg"><i class="fas fa-lightbulb"></i></div>
                            <div class="content">
                                <div class="value" id="total-energy-value"></div>
                                <div class="label">Total Konsumsi</div>
                            </div>
                            <div class="change neutral" id="total-energy-change"></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card skeleton-wrapper" id="summary-card-cost">
                            <div class="icon-bg"><i class="fas fa-wallet"></i></div>
                            <div class="content">
                                <div class="value" id="total-cost-value"></div>
                                <div class="label">Perkiraan Biaya</div>
                            </div>
                            <div class="change neutral" id="total-cost-change"></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card skeleton-wrapper" id="summary-card-uptime">
                            <div class="icon-bg"><i class="fas fa-hourglass-half"></i></div>
                            <div class="content">
                                <div class="value" id="avg-uptime-value"></div>
                                <div class="label">Rata-rata Uptime</div>
                            </div>
                            <div class="change neutral" id="avg-uptime-change"></div>
                        </div>
                    </div>
                </div>

                <!-- Insights & Recommendations -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card insight-card skeleton-wrapper" id="insights-card">
                            <div class="card-header">
                                <i class="fas fa-lightbulb me-2"></i>Insights & Rekomendasi Penghematan
                            </div>
                            <div class="card-body">
                                <div id="overall-savings-insight" class="mb-4">
                                    <h4 class="fw-bold"><span class="skeleton text short"></span></h4>
                                    <p class="lead" id="savings-summary-text"><span class="skeleton text long"></span><span class="skeleton text medium"></span></p>
                                </div>

                                <h5 class="fw-bold mb-3"><span class="skeleton text short"></span></h5>
                                <div id="recommendations-list" class="list-group">
                                    <!-- Skeleton for recommendations -->
                                    <div class="list-group-item d-flex align-items-start mb-2 rounded-3 shadow-sm">
                                        <div class="skeleton icon-circle me-3"></div>
                                        <div>
                                            <h6 class="mb-1 fw-bold"><span class="skeleton text medium"></span></h6>
                                            <p class="mb-1 text-muted small"><span class="skeleton text long"></span></p>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-start mb-2 rounded-3 shadow-sm">
                                        <div class="skeleton icon-circle me-3"></div>
                                        <div>
                                            <h6 class="mb-1 fw-bold"><span class="skeleton text medium"></span></h6>
                                            <p class="mb-1 text-muted small"><span class="skeleton text long"></span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-3">
                        <div class="card chart-card">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-2"></i>Konsumsi Energi
                            </div>
                            <div class="card-body">
                                <div class="chart-controls" id="energy-chart-controls">
                                    <button class="btn btn-outline-primary btn-sm" data-period="hourly">Per Jam</button>
                                    <button class="btn btn-outline-primary btn-sm active" data-period="daily">Harian</button>
                                    <button class="btn btn-outline-primary btn-sm" data-period="weekly">Mingguan</button>
                                    <button class="btn btn-outline-primary btn-sm" data-period="monthly">Bulanan</button>
                                </div>
                                <div class="chart-container">
                                    <div class="chart-loading-overlay" id="energy-chart-loading">
                                        <div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div>
                                    </div>
                                    <div id="energy-chart-message" class="text-center py-3 hidden">
                                        <div class="alert alert-info py-2 mb-0" role="alert">Pilih perangkat atau rentang tanggal.</div>
                                    </div>
                                    <canvas id="energyConsumptionChart" class="hidden"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card chart-card">
                            <div class="card-header">
                                <i class="fas fa-pie-chart me-2"></i>Distribusi Perangkat
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <div class="chart-loading-overlay" id="distribution-chart-loading">
                                        <div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div>
                                    </div>
                                    <div id="distribution-chart-message" class="text-center py-3 hidden">
                                        <div class="alert alert-info py-2 mb-0" role="alert">Pilih "Semua Perangkat" untuk melihat distribusi.</div>
                                    </div>
                                    <canvas id="deviceDistributionChart" class="hidden"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Consumption Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-list-alt me-2"></i>Detail Konsumsi Harian
                    </div>
                    <div class="card-body">
                         <div class="chart-loading-overlay" id="daily-details-loading" style="position: absolute; border-radius: 0;">
                            <div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div>
                        </div>
                        <div id="daily-details-message" class="alert alert-info text-center py-2 hidden">Pilih perangkat atau rentang tanggal.</div>
                        <div class="table-responsive">
                            <table class="table table-hover daily-detail-table">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort-by="date">Tanggal <i class="fas fa-sort"></i></th>
                                        <th id="daily-details-device-header">Perangkat</th>
                                        <th class="sortable" data-sort-by="energy_kwh">Konsumsi (kWh) <i class="fas fa-sort"></i></th>
                                        <th class="sortable" data-sort-by="cost_rp">Biaya (Rp) <i class="fas fa-sort"></i></th>
                                        <th>Status Mesin</th>
                                    </tr>
                                </thead>
                                <tbody id="daily-details-tbody">
                                    <!-- Data akan diisi oleh JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <button id="show-more-daily-details" class="btn btn-outline-secondary btn-sm mt-3 hidden">Tampilkan Lebih Banyak</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script> <!-- Bahasa Indonesia -->
    <script>
        const USER_ID = <?= json_encode($userId) ?>;
        const API_ANALYTICS_URL = '../../api/get_analytics_data.php'; // API untuk data analitik
        const API_ALERTS_URL = '../../api/get_unread_alerts.php'; // API terpisah untuk notifikasi

        let selectedDeviceId = <?= json_encode($selectedDeviceDbId) ?>;
        let selectedDateRange = {
            startDate: null,
            endDate: null
        }; 

        let energyChartInstance = null;
        let distributionChartInstance = null;
        let currentEnergyChartPeriod = 'daily'; // Default period for energy chart

        let allDailyDetailsData = []; // Untuk menyimpan semua data detail harian (untuk sorting/pagination)
        const ITEMS_PER_PAGE = 10; // Jumlah item per halaman untuk tabel detail
        let currentPage = 1; // Halaman saat ini untuk tabel detail

        // --- Helper Functions ---
        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        function updateChangeDisplay(elementId, value) {
            const element = document.getElementById(elementId);
            if (!element) return;

            element.innerHTML = ''; // Clear previous content
            element.classList.remove('positive', 'negative', 'neutral');

            if (value > 0) {
                element.classList.add('positive');
                element.innerHTML = `<i class="fas fa-arrow-up"></i> ${value.toFixed(0)}%`;
            } else if (value < 0) {
                element.classList.add('negative');
                element.innerHTML = `<i class="fas fa-arrow-down"></i> ${Math.abs(value).toFixed(0)}%`;
            } else {
                element.classList.add('neutral');
                element.innerHTML = `<i class="fas fa-minus"></i> 0%`;
            }
        }

        // --- Loading State Functions (Skeleton Loader) ---
        function showSkeleton(elementId) {
            document.getElementById(elementId).classList.add('loading');
        }

        function hideSkeleton(elementId) {
            document.getElementById(elementId).classList.remove('loading');
        }

        function showLoadingOverlay(elementId) {
            document.getElementById(elementId).classList.remove('hidden');
        }

        function hideLoadingOverlay(elementId) {
            document.getElementById(elementId).classList.add('hidden');
        }

        function showMessage(elementId, message, type = 'info') {
            const el = document.getElementById(elementId);
            if (el) {
                el.innerHTML = `<div class="alert alert-${type} py-2 mb-0" role="alert">${message}</div>`;
                el.classList.remove('hidden');
            }
        }

        function hideMessage(elementId) {
            const el = document.getElementById(elementId);
            if (el) {
                el.classList.add('hidden');
            }
        }

        // --- Reset Display Functions ---
        function resetSummaryStats() {
            // Insert skeleton spans into the elements
            document.getElementById('avg-power-value').innerHTML = '<span class="skeleton value"></span>';
            document.getElementById('total-energy-value').innerHTML = '<span class="skeleton value"></span>';
            document.getElementById('total-cost-value').innerHTML = '<span class="skeleton value"></span>';
            document.getElementById('avg-uptime-value').innerHTML = '<span class="skeleton value"></span>';
            
            // For labels, we need to ensure the original text is hidden and skeleton is shown
            // This is handled by CSS now, but we'll ensure the label text is present for when not loading
            // For now, we'll keep the text and let CSS hide it.
            
            // Apply loading class to parent cards
            showSkeleton('summary-card-power');
            showSkeleton('summary-card-energy');
            showSkeleton('summary-card-cost');
            showSkeleton('summary-card-uptime');

            // Reset change display visually (will be hidden by skeleton CSS)
            updateChangeDisplay('avg-power-change', 0);
            updateChangeDisplay('total-energy-change', 0);
            updateChangeDisplay('total-cost-change', 0);
            updateChangeDisplay('avg-uptime-change', 0);
        }

        function resetEnergyChart() {
            if (energyChartInstance) {
                energyChartInstance.destroy();
                energyChartInstance = null;
            }
            document.getElementById('energyConsumptionChart').classList.add('hidden');
            showMessage('energy-chart-message', 'Pilih perangkat atau rentang tanggal.');
            hideLoadingOverlay('energy-chart-loading'); // Pastikan overlay tersembunyi
        }

        function resetDistributionChart() {
            if (distributionChartInstance) {
                distributionChartInstance.destroy();
                distributionChartInstance = null;
            }
            document.getElementById('deviceDistributionChart').classList.add('hidden');
            showMessage('distribution-chart-message', 'Pilih "Semua Perangkat" untuk melihat distribusi.');
            hideLoadingOverlay('distribution-chart-loading'); // Pastikan overlay tersembunyi
        }

        function resetDailyDetails() {
            document.getElementById('daily-details-tbody').innerHTML = '';
            showMessage('daily-details-message', 'Pilih perangkat atau rentang tanggal.');
            document.getElementById('show-more-daily-details').classList.add('hidden'); // Sembunyikan tombol "Tampilkan Lebih Banyak"
            hideLoadingOverlay('daily-details-loading'); // Pastikan overlay tersembunyi
            allDailyDetailsData = []; // Kosongkan data
            currentPage = 1; // Reset halaman
        }

        function resetInsightsAndRecommendations() {
            // Insert skeleton spans into the elements
            document.getElementById('overall-savings-insight').innerHTML = `
                <h4 class="fw-bold"><span class="skeleton text short"></span></h4>
                <p class="lead" id="savings-summary-text"><span class="skeleton text long"></span><span class="skeleton text medium"></span></p>
            `;
            const recList = document.getElementById('recommendations-list');
            recList.innerHTML = `
                <div class="list-group-item d-flex align-items-start mb-2 rounded-3 shadow-sm">
                    <div class="icon-circle me-3"><span class="skeleton icon-circle"></span></div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold"><span class="skeleton text medium"></span></h6>
                        <p class="mb-1 text-muted small"><span class="skeleton text long"></span></p>
                    </div>
                </div>
                <div class="list-group-item d-flex align-items-start mb-2 rounded-3 shadow-sm">
                    <div class="icon-circle me-3"><span class="skeleton icon-circle"></span></div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold"><span class="skeleton text medium"></span></h6>
                        <p class="mb-1 text-muted small"><span class="skeleton text long"></span></p>
                    </div>
                </div>
            `;
            // For the H5 header above recommendations list
            document.querySelector('#insights-card .card-body > h5').innerHTML = '<span class="skeleton text short"></span>';

            showSkeleton('insights-card');
        }

        function resetAllDisplay() {
            resetSummaryStats();
            resetInsightsAndRecommendations(); // Panggil ini sebelum chart/details agar skeleton muncul
            resetEnergyChart();
            resetDistributionChart();
            resetDailyDetails();
        }

        // --- Data Loading Functions ---
        async function updateSummaryStatsAndInsights() {
            if (!selectedDeviceId || !selectedDateRange.startDate || !selectedDateRange.endDate) {
                resetSummaryStats();
                resetInsightsAndRecommendations();
                return;
            }

            // Show skeletons before fetching
            resetSummaryStats(); // This inserts skeleton spans and applies 'loading' class
            resetInsightsAndRecommendations(); // This inserts skeleton spans and applies 'loading' class

            try {
                const response = await fetch(`${API_ANALYTICS_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=summary&start_date=${selectedDateRange.startDate}&end_date=${selectedDateRange.endDate}`);
                const data = await response.json();

                if (data.status === 'success') {
                    // Update Summary Stats
                    if (data.summary_stats) {
                        const stats = data.summary_stats;
                        document.getElementById('avg-power-value').innerText = `${(stats.average_power.value || 0).toFixed(2).replace('.', ',')} W`;
                        document.getElementById('total-energy-value').innerText = `${(stats.total_energy_consumption.value || 0).toFixed(3).replace('.', ',')} kWh`;
                        document.getElementById('total-cost-value').innerText = formatRupiah(stats.total_cost.value || 0);
                        document.getElementById('avg-uptime-value').innerText = `${(stats.average_uptime.value || 0).toFixed(0)}%`;

                        updateChangeDisplay('avg-power-change', stats.average_power.change_percent || 0);
                        updateChangeDisplay('total-energy-change', stats.total_energy_consumption.change_percent || 0);
                        updateChangeDisplay('total-cost-change', stats.total_cost.change_percent || 0);
                        updateChangeDisplay('avg-uptime-change', stats.average_uptime.change_percent || 0);
                    } else {
                        console.error('Failed to load summary stats:', data.message);
                        resetSummaryStats(); 
                    }

                    // Update Insights and Recommendations
                    if (data.insights_recommendations) {
                        const insights = data.insights_recommendations;
                        const savings = insights.overall_savings_potential;
                        if (savings && savings.min_rp !== undefined && savings.max_rp !== undefined) {
                             document.getElementById('overall-savings-insight').innerHTML = `
                                <h4 class="fw-bold">💰 Potensi Penghematan Maksimal Anda</h4>
                                <p class="lead" id="savings-summary-text">
                                    Dengan menerapkan rekomendasi di bawah, Anda berpotensi menghemat antara
                                    <strong>${formatRupiah(savings.min_rp)} hingga ${formatRupiah(savings.max_rp)}</strong>
                                    per bulan, atau setara dengan <strong>${savings.min_percent}% - ${savings.max_percent}%</strong>
                                    dari tagihan listrik Anda saat ini (${formatRupiah(savings.current_monthly_bill_rp)}).
                                </p>
                            `;
                        } else {
                            document.getElementById('overall-savings-insight').innerHTML = `
                                <h4 class="fw-bold">💰 Potensi Penghematan Maksimal Anda</h4>
                                <p class="lead" id="savings-summary-text">Tidak ada potensi penghematan yang dapat dihitung saat ini.</p>
                            `;
                        }
                        // Restore original H5 header text
                        document.querySelector('#insights-card .card-body > h5').innerText = 'Rekomendasi Spesifik';
                        displayRecommendations(insights.recommendations);
                    } else {
                        console.error('Failed to load insights and recommendations:', data.message);
                        resetInsightsAndRecommendations(); 
                    }

                } else {
                    console.error('Failed to load summary and insights:', data.message);
                    resetSummaryStats();
                    resetInsightsAndRecommendations();
                }
            } catch (error) {
                console.error('Error fetching summary stats and insights:', error);
                resetSummaryStats();
                resetInsightsAndRecommendations();
            } finally {
                // Hide skeletons after data is loaded or failed
                hideSkeleton('summary-card-power');
                hideSkeleton('summary-card-energy');
                hideSkeleton('summary-card-cost');
                hideSkeleton('summary-card-uptime');
                hideSkeleton('insights-card');
            }
        }

        function displayRecommendations(recommendationsArray) {
            const recList = document.getElementById('recommendations-list');
            recList.innerHTML = ''; // Clear previous data

            if (recommendationsArray && recommendationsArray.length > 0) {
                recommendationsArray.forEach(rec => {
                    const recItem = document.createElement('div'); 
                    recItem.classList.add('list-group-item', 'd-flex', 'align-items-start', 'mb-2', 'rounded-3', 'shadow-sm');

                    let iconHtml = `<div class="icon-circle me-3"><i class="${rec.icon || 'fas fa-lightbulb'}"></i></div>`;
                    let savingsBadge = '';
                    if (rec.estimated_savings_rp && rec.estimated_savings_rp > 0) {
                        savingsBadge = `<span class="badge bg-success mt-2"><i class="fas fa-dollar-sign"></i> Hemat ${formatRupiah(rec.estimated_savings_rp)}/bulan</span>`;
                    }
                    
                    // Tambahkan tombol aksi jika ada action_link
                    let actionButton = '';
                    if (rec.action_link) {
                        actionButton = `<a href="${rec.action_link}" class="btn btn-sm btn-outline-primary action-button">${rec.action_text || 'Lakukan Ini'}</a>`;
                    }

                    recItem.innerHTML = `
                        ${iconHtml}
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold">${rec.title}</h6>
                            <p class="mb-1 text-muted small">${rec.description}</p>
                            ${savingsBadge}
                        </div>
                        ${actionButton}
                    `;
                    recList.appendChild(recItem);
                });
            } else {
                recList.innerHTML = '<div class="alert alert-info text-center py-2">Tidak ada rekomendasi spesifik saat ini.</div>';
            }
        }


        async function loadEnergyConsumptionChart(period) {
            if (!selectedDeviceId || !selectedDateRange.startDate || !selectedDateRange.endDate) {
                resetEnergyChart();
                return;
            }

            showLoadingOverlay('energy-chart-loading');
            hideMessage('energy-chart-message');
            document.getElementById('energyConsumptionChart').classList.add('hidden');

            try {
                const response = await fetch(`${API_ANALYTICS_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=energy_chart&chart_granularity=${period}&start_date=${selectedDateRange.startDate}&end_date=${selectedDateRange.endDate}`);
                const data = await response.json();

                hideLoadingOverlay('energy-chart-loading');

                if (data.status === 'success' && data.energy_chart_data && data.energy_chart_data.labels.length > 0) {
                    if (energyChartInstance) {
                        energyChartInstance.destroy();
                    }
                    document.getElementById('energyConsumptionChart').classList.remove('hidden');
                    const ctx = document.getElementById('energyConsumptionChart').getContext('2d');
                    energyChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.energy_chart_data.labels,
                            datasets: [{
                                label: 'Konsumsi Energi (kWh)',
                                tension: 0.4, // Lebih smooth
                                backgroundColor: 'rgba(102, 126, 234, 0.2)', /* Warna dari gradient device-select-card */
                                borderColor: 'rgba(102, 126, 234, 1)',    /* Warna dari gradient device-select-card */
                                pointRadius: 4,
                                pointBackgroundColor: 'rgba(102, 126, 234, 1)', /* Warna dari gradient device-select-card */
                                pointBorderColor: 'rgba(255,255,255,0.8)',
                                pointHoverRadius: 6,
                                data: data.energy_chart_data.data,
                                fill: true,
                                borderWidth: 2,
                                hoverBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(45, 55, 72, 0.9)', /* Warna dark grey yang sudah ada */
                                    titleColor: 'white',
                                    bodyColor: 'white',
                                    borderColor: 'rgba(255,255,255,0.2)',
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 6,
                                    callbacks: {
                                        label: function(context) {
                                            return 'Konsumsi: ' + context.parsed.y.toFixed(3).replace('.', ',') + ' kWh';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: {
                                        maxTicksLimit: 10,
                                        font: { size: 10, family: 'Inter' },
                                        color: '#a0aec0' /* Warna teks yang sudah ada */
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        maxTicksLimit: 5,
                                        callback: function(value) {
                                            return value + ' kWh';
                                        },
                                        font: { size: 10, family: 'Inter' },
                                        color: '#a0aec0' /* Warna teks yang sudah ada */
                                    },
                                    grid: { color: 'rgba(0,0,0,0.05)' }
                                }
                            }
                        }
                    });
                } else {
                    document.getElementById('energyConsumptionChart').classList.add('hidden');
                    showMessage('energy-chart-message', data.message || 'Belum ada data konsumsi energi untuk periode ini.', 'info');
                    if (energyChartInstance) {
                        energyChartInstance.destroy();
                        energyChartInstance = null;
                    }
                }
            } catch (error) {
                console.error('Error fetching energy consumption chart data:', error);
                hideLoadingOverlay('energy-chart-loading');
                document.getElementById('energyConsumptionChart').classList.add('hidden');
                showMessage('energy-chart-message', `Gagal memuat grafik konsumsi energi: ${error.message}`, 'danger');
                if (energyChartInstance) {
                    energyChartInstance.destroy();
                    energyChartInstance = null;
                }
            }
        }

        async function loadDeviceDistributionChart() {
            // Hanya tampilkan jika 'Semua Perangkat' dipilih
            if (selectedDeviceId !== 'all' || !selectedDateRange.startDate || !selectedDateRange.endDate) {
                resetDistributionChart();
                return;
            }

            showLoadingOverlay('distribution-chart-loading');
            hideMessage('distribution-chart-message');
            document.getElementById('deviceDistributionChart').classList.add('hidden');

            try {
                const response = await fetch(`${API_ANALYTICS_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=device_distribution&start_date=${selectedDateRange.startDate}&end_date=${selectedDateRange.endDate}`);
                const data = await response.json();

                hideLoadingOverlay('distribution-chart-loading');

                if (data.status === 'success' && data.device_distribution_data && data.device_distribution_data.labels.length > 0) {
                    if (distributionChartInstance) {
                        distributionChartInstance.destroy();
                    }
                    document.getElementById('deviceDistributionChart').classList.remove('hidden');
                    const ctx = document.getElementById('deviceDistributionChart').getContext('2d');
                    distributionChartInstance = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.device_distribution_data.labels,
                            datasets: [{
                                data: data.device_distribution_data.data,
                                backgroundColor: [
                                    '#667eea', '#4fd1c5', '#f6ad55', '#fc8181', '#a3aed0', '#764ba2', '#63b3ed' /* Warna dari gradient dan aksen */
                                ],
                                hoverBackgroundColor: [
                                    '#5a67d8', '#38b2ac', '#ed8936', '#e53e3e', '#8b94b3', '#6b46c1', '#4299e1' /* Warna sedikit lebih gelap/terang */
                                ],
                                borderWidth: 2,
                                hoverBorderColor: 'white'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: { size: 11, family: 'Inter' },
                                        color: '#2d3748', /* Warna teks yang sudah ada */
                                        boxWidth: 15,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(45, 55, 72, 0.9)', /* Warna dark grey yang sudah ada */
                                    titleColor: 'white',
                                    bodyColor: 'white',
                                    borderColor: 'rgba(255,255,255,0.2)',
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 6,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed !== null) {
                                                label += context.parsed.toFixed(2) + '%'; // Assuming data is percentage
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    document.getElementById('deviceDistributionChart').classList.add('hidden');
                    showMessage('distribution-chart-message', data.message || 'Belum ada data distribusi perangkat untuk periode ini.', 'info');
                    if (distributionChartInstance) {
                        distributionChartInstance.destroy();
                        distributionChartInstance = null;
                    }
                }
            } catch (error) {
                console.error('Error fetching device distribution chart data:', error);
                hideLoadingOverlay('distribution-chart-loading');
                document.getElementById('deviceDistributionChart').classList.add('hidden');
                showMessage('distribution-chart-message', `Gagal memuat grafik distribusi perangkat: ${error.message}`, 'danger');
                if (distributionChartInstance) {
                    distributionChartInstance.destroy();
                    distributionChartInstance = null;
                }
            }
        }

        // Fungsi untuk menampilkan data detail harian ke tabel
        function renderDailyDetails(dataToDisplay) {
            const tbody = document.getElementById('daily-details-tbody');
            tbody.innerHTML = ''; // Clear previous data

            const deviceHeader = document.getElementById('daily-details-device-header');
            if (selectedDeviceId === 'all') {
                deviceHeader.classList.remove('hidden');
            } else {
                deviceHeader.classList.add('hidden');
            }

            if (dataToDisplay && dataToDisplay.length > 0) {
                hideMessage('daily-details-message'); // Hide message if data is available
                dataToDisplay.forEach(detail => {
                    const row = tbody.insertRow();
                    row.insertCell().innerText = detail.date;
                    
                    if (selectedDeviceId === 'all') {
                        row.insertCell().innerText = detail.device_name || 'N/A';
                    }

                    row.insertCell().innerText = `${(detail.energy_kwh || 0).toFixed(3).replace('.', ',')} kWh`;
                    row.insertCell().innerText = formatRupiah(detail.cost_rp || 0);

                    const statusCell = row.insertCell();
                    const machineStatus = detail.machine_status || 'OFF';
                    let badgeClass = 'idle'; // Default
                    if (machineStatus === 'ON') {
                        badgeClass = 'active';
                    } else if (machineStatus === 'OFF') {
                        badgeClass = 'idle';
                    } else if (machineStatus === 'WARNING') { // Contoh status lain
                        badgeClass = 'warning';
                    }
                    statusCell.innerHTML = `<span class="status-badge ${badgeClass}"><span class="dot"></span> ${machineStatus}</span>`;
                });
            } else {
                showMessage('daily-details-message', 'Belum ada detail konsumsi harian untuk perangkat atau rentang tanggal ini.', 'info');
            }
        }

        // Fungsi untuk memuat data detail harian (termasuk sorting & pagination)
        async function loadDailyConsumptionDetails() {
            if (!selectedDeviceId || !selectedDateRange.startDate || !selectedDateRange.endDate) {
                resetDailyDetails();
                return;
            }

            showLoadingOverlay('daily-details-loading');
            hideMessage('daily-details-message');
            document.getElementById('show-more-daily-details').classList.add('hidden'); // Sembunyikan tombol

            try {
                const response = await fetch(`${API_ANALYTICS_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=daily_details&start_date=${selectedDateRange.startDate}&end_date=${selectedDateRange.endDate}`);
                const data = await response.json();

                hideLoadingOverlay('daily-details-loading');

                if (data.status === 'success' && data.daily_consumption_details && data.daily_consumption_details.length > 0) {
                    allDailyDetailsData = data.daily_consumption_details; // Simpan semua data
                    currentPage = 1; // Reset ke halaman pertama
                    renderDailyDetails(allDailyDetailsData.slice(0, ITEMS_PER_PAGE)); // Tampilkan halaman pertama
                    
                    if (allDailyDetailsData.length > ITEMS_PER_PAGE) {
                        document.getElementById('show-more-daily-details').classList.remove('hidden');
                    }
                } else {
                    showMessage('daily-details-message', data.message || 'Belum ada detail konsumsi harian untuk perangkat atau rentang tanggal ini.', 'info');
                    allDailyDetailsData = []; // Kosongkan
                }
            } catch (error) {
                console.error('Error fetching daily consumption details:', error);
                hideLoadingOverlay('daily-details-loading');
                showMessage('daily-details-message', `Gagal memuat detail konsumsi harian: ${error.message}`, 'danger');
                allDailyDetailsData = []; // Kosongkan
            }
        }

        // Fungsi untuk memuat lebih banyak data detail harian (pagination sederhana)
        function showMoreDailyDetails() {
            currentPage++;
            const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
            const endIndex = startIndex + ITEMS_PER_PAGE;
            const dataToAppend = allDailyDetailsData.slice(startIndex, endIndex);

            const tbody = document.getElementById('daily-details-tbody');
            const deviceHeaderHidden = document.getElementById('daily-details-device-header').classList.contains('hidden');

            dataToAppend.forEach(detail => {
                const row = tbody.insertRow();
                row.insertCell().innerText = detail.date;
                
                if (!deviceHeaderHidden) { // Hanya tampilkan jika header perangkat tidak tersembunyi
                    row.insertCell().innerText = detail.device_name || 'N/A';
                }

                row.insertCell().innerText = `${(detail.energy_kwh || 0).toFixed(3).replace('.', ',')} kWh`;
                row.insertCell().innerText = formatRupiah(detail.cost_rp || 0);

                const statusCell = row.insertCell();
                const machineStatus = detail.machine_status || 'OFF';
                let badgeClass = 'idle';
                if (machineStatus === 'ON') {
                    badgeClass = 'active';
                } else if (machineStatus === 'OFF') {
                    badgeClass = 'idle';
                } else if (machineStatus === 'WARNING') {
                    badgeClass = 'warning';
                }
                statusCell.innerHTML = `<span class="status-badge ${badgeClass}"><span class="dot"></span> ${machineStatus}</span>`;
            });

            if (endIndex >= allDailyDetailsData.length) {
                document.getElementById('show-more-daily-details').classList.add('hidden');
            }
        }

        // --- Navbar Alert Loading Function ---
        function fetchNavbarAlerts() {
            if (!USER_ID) {
                console.error("USER_ID not defined for fetching navbar alerts.");
                return;
            }
            fetch(`${API_ALERTS_URL}?user_id=${USER_ID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('unread-alerts-count').innerText = data.unread_count;
                        const alertsList = document.getElementById('recent-alerts-list');
                        alertsList.innerHTML = ''; // Bersihkan daftar sebelumnya

                        if (data.recent_alerts.length > 0) {
                            data.recent_alerts.forEach(alert => {
                                const alertItem = document.createElement('a');
                                alertItem.className = 'dropdown-item d-flex align-items-center alert-item';
                                // Link ke notification.php, relatif dari analytics/overview.php
                                alertItem.href = `../settings/notification.php?alert_id=${alert.id}`;

                                let iconClass = 'fas fa-info-circle';
                                let iconBgClass = 'info'; // Menggunakan kelas Bootstrap untuk warna latar belakang ikon
                                if (alert.severity === 'warning') {
                                    iconClass = 'fas fa-exclamation-triangle';
                                    iconBgClass = 'warning';
                                } else if (alert.severity === 'error' || alert.severity === 'critical') {
                                    iconClass = 'fas fa-exclamation-circle';
                                    iconBgClass = 'critical';
                                } else if (alert.severity === 'success') {
                                    iconClass = 'fas fa-check-circle';
                                    iconBgClass = 'success';
                                }

                                alertItem.classList.add(iconBgClass); // Tambahkan kelas info/warning/critical ke item
                                if (!alert.is_read) {
                                    alertItem.classList.add('unread');
                                }

                                alertItem.innerHTML = `
                                    <div class="icon-circle bg-${iconBgClass}">
                                        <i class="${iconClass} text-white"></i>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">${alert.created_at_formatted}</div>
                                        <span class="font-weight-bold">${alert.message.substring(0, 50)}...</span>
                                    </div>
                                `;
                                alertsList.appendChild(alertItem);
                            });
                        } else {
                            alertsList.innerHTML = '<a class="dropdown-item text-center small text-gray-500 py-2">Tidak ada peringatan baru.</a>';
                        }
                    } else {
                        console.error('Failed to fetch navbar alerts:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching navbar alerts:', error);
                });
        }


        // --- Event Handlers ---
        // Fungsi utama untuk memuat ulang semua data di halaman
        function handleDataReload(triggeredByPreset = false) {
            if (!selectedDeviceId || !selectedDateRange.startDate || !selectedDateRange.endDate) {
                resetAllDisplay();
                return;
            }

            // Tentukan periode grafik energi secara otomatis berdasarkan rentang tanggal
            const start = new Date(selectedDateRange.startDate);
            const end = new Date(selectedDateRange.endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let recommendedPeriod = 'daily';
            if (diffDays <= 2) { // 1-2 hari
                recommendedPeriod = 'hourly';
            } else if (diffDays <= 30) { // Sampai 1 bulan
                recommendedPeriod = 'daily';
            } else if (diffDays <= 90) { // Sampai 3 bulan
                recommendedPeriod = 'weekly';
            } else { // Lebih dari 3 bulan
                recommendedPeriod = 'monthly';
            }
            
            // Hanya ganti currentEnergyChartPeriod jika bukan dari preset atau jika periode otomatis berbeda
            if (!triggeredByPreset || currentEnergyChartPeriod !== recommendedPeriod) {
                currentEnergyChartPeriod = recommendedPeriod;
            }

            // Update tombol aktif di kontrol grafik energi
            document.querySelectorAll('#energy-chart-controls .btn').forEach(btn => {
                if (btn.dataset.period === currentEnergyChartPeriod) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            updateSummaryStatsAndInsights();
            loadEnergyConsumptionChart(currentEnergyChartPeriod);
            loadDeviceDistributionChart(); // Conditional display handled inside
            loadDailyConsumptionDetails(); // Conditional display handled inside
        }

        function handleDeviceChange() {
            selectedDeviceId = document.getElementById('device-selector').value;

            // Update URL
            const url = new URL(window.location);
            if (selectedDeviceId && selectedDeviceId !== 'all') {
                url.searchParams.set('device_id', selectedDeviceId);
            } else {
                url.searchParams.delete('device_id');
            }
            window.history.pushState({}, '', url);

            handleDataReload();
        }

        function handleEnergyChartPeriodChange(event) {
            document.querySelectorAll('#energy-chart-controls .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            currentEnergyChartPeriod = event.target.dataset.period;
            loadEnergyConsumptionChart(currentEnergyChartPeriod);
        }

        // Fungsi untuk mengurutkan tabel detail harian
        let currentSortColumn = 'date';
        let currentSortDirection = 'asc'; // 'asc' atau 'desc'

        function sortDailyDetails(column) {
            if (currentSortColumn === column) {
                currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }

            // Hapus kelas sort dari semua header
            document.querySelectorAll('.daily-detail-table th.sortable').forEach(th => {
                th.classList.remove('asc', 'desc');
            });
            // Tambahkan kelas sort ke header yang sedang diurutkan
            document.querySelector(`.daily-detail-table th[data-sort-by="${column}"]`).classList.add(currentSortDirection);


            allDailyDetailsData.sort((a, b) => {
                let valA = a[column];
                let valB = b[column];

                // Konversi ke angka jika kolomnya numerik
                if (column === 'energy_kwh' || column === 'cost_rp') {
                    valA = parseFloat(valA);
                    valB = parseFloat(valB);
                } else if (column === 'date') {
                    valA = new Date(valA);
                    valB = new Date(valB);
                }

                if (valA < valB) {
                    return currentSortDirection === 'asc' ? -1 : 1;
                }
                if (valA > valB) {
                    return currentSortDirection === 'asc' ? 1 : -1;
                }
                return 0;
            });

            currentPage = 1; // Reset pagination setelah sort
            renderDailyDetails(allDailyDetailsData.slice(0, ITEMS_PER_PAGE));
            document.getElementById('show-more-daily-details').classList.remove('hidden');
            if (allDailyDetailsData.length <= ITEMS_PER_PAGE) {
                document.getElementById('show-more-daily-details').classList.add('hidden');
            }
        }


        // --- Initial Load and Intervals ---
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.getElementById('menu-toggle').addEventListener('click', function() {
                document.getElementById('wrapper').classList.toggle('toggled');
            });

            // Inisialisasi Flatpickr untuk pemilihan rentang tanggal
            const fp = flatpickr("#date-range-picker", {
                mode: "range",
                dateFormat: "Y-m-d",
                locale: "id", // Set locale to Indonesian
                // Default ke 7 Hari Terakhir
                defaultDate: [new Date(new Date().setDate(new Date().getDate() - 6)), new Date()], 
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        selectedDateRange.startDate = instance.formatDate(selectedDates[0], "Y-m-d");
                        selectedDateRange.endDate = instance.formatDate(selectedDates[1], "Y-m-d");
                        // Hapus status aktif dari semua preset saat tanggal diubah manual
                        document.querySelectorAll('.date-range-presets .btn').forEach(btn => btn.classList.remove('active'));
                        handleDataReload();
                    }
                }
            });

            // Set initial date range from Flatpickr default
            selectedDateRange.startDate = fp.formatDate(fp.selectedDates[0], "Y-m-d");
            selectedDateRange.endDate = fp.formatDate(fp.selectedDates[1], "Y-m-d");

            // Event listener untuk preset tanggal
            document.querySelectorAll('.date-range-presets .btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.date-range-presets .btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const preset = this.dataset.preset;
                    let startDate, endDate;
                    const today = new Date();
                    today.setHours(0,0,0,0); // Reset time to start of day for consistent date ranges

                    switch (preset) {
                        case 'today':
                            startDate = today;
                            endDate = today;
                            break;
                        case 'yesterday':
                            startDate = new Date(today);
                            startDate.setDate(today.getDate() - 1);
                            endDate = startDate;
                            break;
                        case 'last7days':
                            startDate = new Date(today);
                            startDate.setDate(today.getDate() - 6);
                            endDate = today;
                            break;
                        case 'thismonth':
                            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                            endDate = today;
                            break;
                        case 'lastmonth':
                            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                            endDate = new Date(today.getFullYear(), today.getMonth(), 0); // Last day of previous month
                            break;
                        case 'thisyear':
                            startDate = new Date(today.getFullYear(), 0, 1);
                            endDate = today;
                            break;
                        default: // Default to last 7 days if something goes wrong
                            startDate = new Date(today);
                            startDate.setDate(today.getDate() - 6);
                            endDate = today;
                    }
                    fp.setDate([startDate, endDate], true); // Update Flatpickr and trigger onChange
                    selectedDateRange.startDate = fp.formatDate(startDate, "Y-m-d");
                    selectedDateRange.endDate = fp.formatDate(endDate, "Y-m-d");
                    handleDataReload(true); // Beri tahu bahwa ini dari preset
                });
            });


            // Set up event listener for device selector
            const deviceSelector = document.getElementById('device-selector');
            if (deviceSelector) {
                deviceSelector.addEventListener('change', handleDeviceChange);
            }

            // Set up event listeners for energy chart period buttons
            document.querySelectorAll('#energy-chart-controls .btn').forEach(button => {
                button.addEventListener('click', handleEnergyChartPeriodChange);
            });

            // Event listener untuk tombol "Tampilkan Lebih Banyak"
            document.getElementById('show-more-daily-details').addEventListener('click', showMoreDailyDetails);

            // Event listener untuk sorting tabel
            document.querySelectorAll('.daily-detail-table th.sortable').forEach(header => {
                header.addEventListener('click', function() {
                    sortDailyDetails(this.dataset.sortBy);
                });
            });


            // Initial load
            handleDataReload(); // Call once after initial setup
            fetchNavbarAlerts(); // Panggil saat halaman dimuat

            // Sync button functionality
            document.getElementById('sync-button').addEventListener('click', function(event) {
                event.preventDefault();
                const syncIcon = this.querySelector('i');
                syncIcon.classList.add('fa-spin');
                this.classList.add('disabled');

                handleDataReload(); // Reload all data for this page
                fetchNavbarAlerts();

                setTimeout(() => {
                    syncIcon.classList.remove('fa-spin');
                    this.classList.remove('disabled');
                    console.log('Analytics data manually synced.');
                }, 1500);
            });

            // Set intervals for periodic updates (smart refresh strategy)
            setInterval(updateSummaryStatsAndInsights, 60000); // Ringkasan & Insight setiap 60 detik
            setInterval(fetchNavbarAlerts, 10000); // Notifikasi setiap 10 detik

            // Grafik dan Detail Harian hanya di-reload saat ada perubahan pilihan atau sync manual
            // atau bisa juga dengan interval yang lebih panjang, misal 5 menit sekali
            setInterval(() => {
                if (selectedDeviceId && selectedDateRange.startDate && selectedDateRange.endDate) {
                    loadEnergyConsumptionChart(currentEnergyChartPeriod);
                    loadDeviceDistributionChart();
                    loadDailyConsumptionDetails();
                }
            }, 300000); // Setiap 5 menit (300000 ms)
        });
    </script>
</body>
</html>
