<?php
// File: dashboard/index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/database.php'; // Pastikan ini ada dan berisi koneksi PDO

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$userName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Pengguna';
$userEmail = $currentUser['email'] ?? 'email@example.com';
$totalDevices = 0;
$onlineDevices = 0;

$STALE_DATA_THRESHOLD_SECONDS = 300; // 5 menit

try {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("
        SELECT
            COUNT(id) AS total,
            SUM(CASE WHEN updated_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, updated_at, NOW()) <= ? THEN 1 ELSE 0 END) AS online_count
        FROM devices
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$STALE_DATA_THRESHOLD_SECONDS, $userId]);
    $deviceCounts = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDevices = $deviceCounts['total'];
    $onlineDevices = $deviceCounts['online_count'];

} catch (PDOException $e) {
    error_log("Error fetching initial dashboard data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard Minimalis Styling */
        .welcome-header {
            margin-bottom: 24px;
        }
        .welcome-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        .welcome-header p {
            font-size: 0.9rem;
            color: #a0aec0;
        }

        /* Stat Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 20px;
            background: #f8f9fc;
            transition: all 0.2s ease;
            height: 100%;
        }
        .stat-card:hover {
            background: #eef2f7;
            transform: translateY(-2px);
        }
        .stat-card .stat-icon {
            font-size: 1.8rem;
            color: #667eea;
            margin-bottom: 12px;
        }
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* Chart Card */
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
            padding: 14px 20px;
        }
        .chart-card .card-body {
            padding: 16px;
        }
        .chart-card .nav-tabs .nav-link {
            border: none;
            color: #a0aec0;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 8px;
        }
        .chart-card .nav-tabs .nav-link.active {
            color: #667eea;
            background: #ebf4ff;
        }
        .chart-card .nav-tabs .nav-link:hover {
            color: #667eea;
            background: #f0f5ff;
        }

        /* Insight Card */
        .insight-card {
            border: none;
            border-radius: 12px;
            height: 100%;
        }
        .insight-card .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 14px 20px;
        }
        .insight-card .card-body {
            padding: 16px 20px;
        }
        .insight-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .insight-item:last-child {
            border-bottom: none;
        }
        .insight-item i {
            font-size: 1.2rem;
            color: #667eea; /* Default blue for insights */
            margin-top: 2px;
        }
        .insight-item.success i { /* Green for positive insights */
            color: #38a169;
        }
        .insight-item.warning i { /* Orange for warning insights */
            color: #d69e2e;
        }
        .insight-item.danger i { /* Red for critical insights */
            color: #e53e3e;
        }
        .insight-item .text {
            flex: 1;
        }
        .insight-item .text span {
            display: block;
            font-size: 0.85rem;
            color: #2d3748;
        }
        .insight-item .text small {
            font-size: 0.75rem;
            color: #a0aec0;
        }

        /* Device List Card */
        .device-list-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        .device-list-card .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 14px 20px;
        }
        .device-list-card .card-body {
            padding: 8px 20px;
        }
        .device-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .device-item:last-child {
            border-bottom: none;
        }
        .device-item i {
            font-size: 1.2rem;
            color: #667eea;
            width: 24px;
            text-align: center;
        }
        .device-item .device-info {
            flex: 1;
        }
        .device-item .device-info .device-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: #2d3748;
        }
        .device-item .device-info .device-details {
            font-size: 0.75rem;
            color: #a0aec0;
        }
        .device-item .badge {
            font-size: 0.7rem;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* --- NEW: CSS untuk Alert Section --- */
        .alert-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
            display: flex;
            align-items: center;
        }
        .alert-item:last-child {
            border-bottom: none;
        }
        .alert-item .icon {
            font-size: 1.2rem;
            margin-right: 10px;
        }
        .alert-item.info .icon { color: #0d6efd; }
        .alert-item.warning .icon { color: #ffc107; }
        .alert-item.danger .icon { color: #dc3545; } /* Menggunakan 'danger' untuk critical/error */
        .alert-item.unread {
            background-color: #e0f2f7; /* Warna latar belakang untuk peringatan yang belum dibaca */
            border-left: 5px solid #0d6efd;
            padding-left: 15px;
        }
        .alert-item .alert-message {
            flex-grow: 1;
            font-size: 0.9rem;
        }
        .alert-item .alert-time {
            font-size: 0.75rem;
            color: #6c757d;
            white-space: nowrap;
            margin-left: 10px;
        }
        /* --- END NEW CSS --- */


        /* Responsive */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -var(--sidebar-width);
                position: fixed;
                z-index: 1000;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                width: 100%;
                margin-left: 0;
            }
            .toggle-button {
                display: block;
            }
            .navbar-nav {
                flex-direction: row;
                align-items: center;
            }
            .navbar-nav .nav-item {
                margin-left: 10px;
            }
            .stat-card .stat-value {
                font-size: 1.2rem;
            }
        }
        @media (min-width: 769px) {
            .toggle-button {
                display: none;
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
                <a href="index.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="devices/list.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-microchip"></i> Perangkat Saya
                </a>
                <a href="monitoring/history.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history"></i> Data Historis
                </a>
                <a href="analytics/overview.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-pie"></i> Analisis & Laporan
                </a>
                <a href="settings/notification.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-bell"></i> Notifikasi
                </a>
                <a href="settings/profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-cog"></i> Pengaturan Akun
                </a>
                <a href="../auth/logout.php" class="list-group-item list-group-item-action text-danger">
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
                            <a class="nav-link" href="#" id="sync-button"><i class="fas fa-sync-alt"></i> Sync</a>
                        </li>

                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="unread-navbar-alerts-count">
                                    0
                                    <span class="visually-hidden">unread messages</span>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="alertsDropdown" style="min-width: 300px;">
                                <h6 class="dropdown-header">Pusat Peringatan</h6>
                                <div id="recent-navbar-alerts-list">
                                    <!-- Alerts will be loaded here by JavaScript -->
                                    <a class="dropdown-item text-center small text-gray-500 py-2">Memuat peringatan...</a>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500 py-2" href="settings/notification.php">Lihat Semua Peringatan</a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="#">Profil Saya</a>
                                <a class="dropdown-item" href="#">Pengaturan</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="../auth/logout.php">Logout</a>
                            </div>
                        </li>
                        <li class="nav-item ms-3">
                            <a class="nav-link text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Welcome Header -->
                <div class="welcome-header">
                    <h1>Selamat Datang, <?= htmlspecialchars($userName) ?>!</h1>
                    <p>Dashboard monitoring energi Anda</p>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                            <div class="stat-value" id="total-power-value">0 W</div>
                            <div class="stat-label">Total Daya Saat Ini</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-microchip"></i></div>
                            <div class="stat-value" id="active-machines-value">0/0</div>
                            <div class="stat-label">Mesin Aktif</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                            <div class="stat-value" id="energy-today-value">0,00 kWh</div>
                            <div class="stat-label">Konsumsi Hari Ini</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-value" id="energy-this-month-value">0,00 kWh</div>
                            <div class="stat-label">Konsumsi Bulan Ini</div>
                        </div>
                    </div>
                </div>

                <!-- NEW: Stat Cards (Biaya & Proyeksi Total) -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-money-bill-alt"></i></div>
                            <div class="stat-value" id="cost-today-value">Rp 0</div>
                            <div class="stat-label">Biaya Hari Ini</div>
                        </div>
                    </div>
                    <!-- Kartu "Proyeksi Biaya Mingguan" dihapus dan diganti dengan "Alat Paling Boros" -->
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-fire"></i></div>
                            <div class="stat-value" id="most-wasteful-device-stat-value">Memuat...</div>
                            <div class="stat-label">Alat Paling Boros (7 Hari Terakhir)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-value" id="cost-monthly-projection-value">Rp 0</div>
                            <div class="stat-label">Proyeksi Biaya Bulanan</div>
                        </div>
                    </div>
                </div>

                <!-- Chart & Insight Row -->
                <div class="row g-4 mb-4">
                    <!-- Chart -->
                    <div class="col-lg-8">
                        <div class="card chart-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-line me-2"></i>Grafik Konsumsi</span>
                                <ul class="nav nav-tabs" id="chartTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="realtime-tab" data-bs-toggle="tab" data-bs-target="#realtime" type="button" role="tab" aria-controls="realtime" aria-selected="true">24 Jam</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="harian-tab" data-bs-toggle="tab" data-bs-target="#harian" type="button" role="tab" aria-controls="harian" aria-selected="false">7 Hari</button>
                                    </li>
                                    <!-- Jika ada tab lain, tambahkan di sini -->
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="chartTabContent">
                                    <div class="tab-pane fade show active" id="realtime" role="tabpanel" aria-labelledby="realtime-tab">
                                        <div class="chart-container" style="position: relative; height: 250px;">
                                            <canvas id="realtimeAreaChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="harian" role="tabpanel" aria-labelledby="harian-tab">
                                        <div class="chart-container" style="position: relative; height: 250px;">
                                            <canvas id="harianAreaChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insight -->
                    <div class="col-lg-4">
                        <div class="card insight-card">
                            <div class="card-header">
                                <i class="fas fa-lightbulb me-2"></i>Insight & Rekomendasi
                            </div>
                            <div class="card-body" id="insight-container">
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Memuat...</span>
                                    </div>
                                    <p class="mt-2 text-muted small">Menganalisis data...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>



                <!-- NEW: Recent Alerts Card (for loadDashboardAlerts) -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-12">
                        <div class="card insight-card">
                            <div class="card-header">
                                <i class="fas fa-bell me-2"></i>Peringatan Terbaru
                                <span class="badge bg-danger ms-2" id="unread-alerts-badge" style="display:none;">0 Baru</span>
                            </div>
                            <div class="card-body" id="alerts-list-container">
                                <div id="alerts-loading-message" class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Memuat...</span>
                                    </div>
                                    <p class="mt-2 text-muted small">Memuat peringatan...</p>
                                </div>
                                <div id="no-alerts-message" class="alert alert-info text-center py-2 mb-0" role="alert" style="display:none;">
                                    Tidak ada peringatan baru saat ini.
                                </div>
                            </div>
                            <div class="card-footer text-end" style="background:white; border-top:1px solid #e2e8f0;">
                                <a href="settings/notification.php" class="text-primary text-decoration-none small fw-bold" id="view-all-alerts-link" style="display:none;">Lihat Semua Peringatan <i class="fas fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- NEW: Per-Device Costs Card -->
                <div class="card device-list-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-dollar-sign me-2"></i>Biaya Per Perangkat</span>
                        <select class="form-select form-select-sm w-auto" id="device-cost-period-selector">
                            <option value="daily">Harian</option>
                            <option value="weekly_projection">Proyeksi Mingguan</option>
                            <option value="monthly_projection">Proyeksi Bulanan</option>
                        </select>
                    </div>
                    <div class="card-body" id="per-device-costs-list">
                        <div class="alert alert-info text-center py-2 mb-0" role="alert">
                            Memuat biaya per perangkat...
                        </div>
                    </div>
                </div>


                <!-- Recent Devices -->
                <div class="card device-list-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-microchip me-2"></i>Perangkat Terbaru</span>
                        <a href="devices/add.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Tambah
                        </a>
                    </div>
                    <div class="card-body" id="recent-devices-list">
                        <div class="alert alert-info text-center py-2 mb-0" role="alert">
                            Memuat perangkat terbaru...
                        </div>
                        <div class="text-end mt-3">
                            <a href="devices/list.php" class="text-primary text-decoration-none small fw-bold">Lihat Semua Perangkat <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    // ... kode yang sudah ada untuk inisialisasi dan setInterval ...

    // Tambahkan event listener untuk tombol Sync
    document.getElementById('sync-button').addEventListener('click', function(event) {
        event.preventDefault(); // Mencegah perilaku default href="#"

        // Opsional: Berikan feedback visual bahwa sinkronisasi sedang berlangsung
        const syncIcon = this.querySelector('i');
        syncIcon.classList.add('fa-spin'); // Tambahkan animasi berputar
        this.classList.add('disabled'); // Nonaktifkan tombol sementara

        // Panggil semua fungsi pembaruan data yang relevan
        updateDashboardSummary();
        loadDashboardAlerts();
        fetchNavbarAlerts();
        loadRealtimeChart();
        loadDailyChart();

        // Setelah semua pembaruan dimulai (asumsi async, tidak menunggu selesai)
        // Anda bisa menunda penghapusan animasi untuk memberikan kesan "bekerja"
        setTimeout(() => {
            syncIcon.classList.remove('fa-spin');
            this.classList.remove('disabled');
            console.log('Dashboard data manually synced.');
        }, 1500); // Tunggu 1.5 detik sebelum menghentikan animasi
    });
});

    const USER_ID = <?= json_encode($userId) ?>;
    const API_BASE_URL = '/lantaburateknologi/api/'; // Sesuaikan jika path API Anda berbeda

    // Toggle sidebar
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('wrapper').classList.toggle('toggled');
    });

    // --- Chart.js Initialization ---
    let realtimeAreaChartInstance;
    let harianAreaChartInstance;

    // Fungsi pembantu untuk membuat/memperbarui grafik
    function createChart(canvasId, type, labels, dataPoints, labelText, backgroundColor, borderColor, unit, tooltipCallback) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        const ctx = canvas.getContext('2d');
        
        // Hancurkan grafik yang ada jika ada
        if (window[canvasId + 'Instance']) {
            window[canvasId + 'Instance'].destroy();
            window[canvasId + 'Instance'] = null;
        }
        
        const chart = new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: labelText,
                    lineTension: 0.3,
                    backgroundColor: backgroundColor,
                    borderColor: borderColor,
                    pointRadius: 3,
                    pointBackgroundColor: borderColor,
                    pointBorderColor: "rgba(255,255,255,0.8)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: borderColor,
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
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
                            label: tooltipCallback || function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + unit;
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
                        beginAtZero: true,
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
        
        window[canvasId + 'Instance'] = chart; // Simpan instance grafik secara global
        return chart;
    }

    function loadRealtimeChart() {
        const canvas = document.getElementById('realtimeAreaChart');
        if (!canvas) return;
        
        const container = canvas.parentNode;
        
        // Hapus pesan sebelumnya
        const existingMsg = container.querySelector('.alert');
        if (existingMsg) existingMsg.remove();
        canvas.style.display = 'block'; // Pastikan kanvas terlihat pada awalnya

        fetch(API_BASE_URL + `get_dashboard_chart_data.php?user_id=${USER_ID}&chart_type=realtime_power`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Realtime chart data:', data); // Debugging
                
                if (data.status === 'success' && data.labels && data.labels.length > 0 && data.data && data.data.length > 0) {
                    realtimeAreaChartInstance = createChart(
                        'realtimeAreaChart',
                        'line',
                        data.labels,
                        data.data,
                        "Total Daya (W)",
                        "rgba(67, 97, 238, 0.2)",
                        "rgba(67, 97, 238, 1)",
                        " W",
                        function(context) {
                            return 'Daya: ' + context.parsed.y.toFixed(2).replace('.', ',') + ' W';
                        }
                    );
                } else {
                    canvas.style.display = 'none';
                    const msg = document.createElement('div');
                    msg.className = 'alert alert-info text-center py-2 mb-0';
                    msg.role = 'alert';
                    msg.innerText = data.message || 'Belum ada data daya untuk 24 jam terakhir.';
                    container.appendChild(msg);
                    
                    if (realtimeAreaChartInstance) {
                        realtimeAreaChartInstance.destroy();
                        realtimeAreaChartInstance = null;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading realtime chart data:', error);
                canvas.style.display = 'none';
                const msg = document.createElement('div');
                msg.className = 'alert alert-danger text-center py-2 mb-0';
                msg.role = 'alert';
                msg.innerText = 'Gagal memuat data grafik: ' + error.message;
                container.appendChild(msg);
                
                if (realtimeAreaChartInstance) {
                    realtimeAreaChartInstance.destroy();
                    realtimeAreaChartInstance = null;
                }
            });
    }

    function loadDailyChart() {
        const canvas = document.getElementById('harianAreaChart');
        if (!canvas) return;
        
        const container = canvas.parentNode;
        
        // Hapus pesan sebelumnya
        const existingMsg = container.querySelector('.alert');
        if (existingMsg) existingMsg.remove();
        canvas.style.display = 'block'; // Pastikan kanvas terlihat pada awalnya

        fetch(API_BASE_URL + `get_dashboard_chart_data.php?user_id=${USER_ID}&chart_type=daily_energy`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Daily chart data:', data); // Debugging
                
                if (data.status === 'success' && data.labels && data.labels.length > 0 && data.data && data.data.length > 0) {
                    harianAreaChartInstance = createChart(
                        'harianAreaChart',
                        'line',
                        data.labels,
                        data.data,
                        "Total Energi (kWh)",
                        "rgba(255, 193, 7, 0.2)",
                        "rgba(255, 193, 7, 1)",
                        " kWh",
                        function(context) {
                            return 'Energi: ' + context.parsed.y.toFixed(3).replace('.', ',') + ' kWh';
                        }
                    );
                } else {
                    canvas.style.display = 'none';
                    const msg = document.createElement('div');
                    msg.className = 'alert alert-info text-center py-2 mb-0';
                    msg.role = 'alert';
                    msg.innerText = data.message || 'Belum ada data energi untuk 7 hari terakhir.';
                    container.appendChild(msg);
                    
                    if (harianAreaChartInstance) {
                        harianAreaChartInstance.destroy();
                        harianAreaChartInstance = null;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading daily chart data:', error);
                canvas.style.display = 'none';
                const msg = document.createElement('div');
                msg.className = 'alert alert-danger text-center py-2 mb-0';
                msg.role = 'alert';
                msg.innerText = 'Gagal memuat data grafik: ' + error.message;
                container.appendChild(msg);
                
                if (harianAreaChartInstance) {
                    harianAreaChartInstance.destroy();
                    harianAreaChartInstance = null;
                }
            });
    }


function updateDashboardSummary() {
    fetch(API_BASE_URL + `get_dashboard_summary.php?user_id=${USER_ID}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Perbarui Stat Cards (Energi & Daya)
                document.getElementById('total-power-value').innerText = 
                    parseFloat(data.total_power || 0).toFixed(2).replace('.', ',') + ' W';
                document.getElementById('active-machines-value').innerText = 
                    (data.active_machines_count || 0) + '/' + (data.total_devices || 0);
                document.getElementById('energy-today-value').innerText = 
                    parseFloat(data.total_energy_today || 0).toFixed(2).replace('.', ',') + ' kWh';
                document.getElementById('energy-this-month-value').innerText = 
                    parseFloat(data.total_energy_this_month || 0).toFixed(2).replace('.', ',') + ' kWh';

                // Perbarui Stat Cards (Biaya & Proyeksi Total)
                document.getElementById('cost-today-value').innerText = 
                    'Rp ' + parseFloat(data.estimated_cost_today || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                
                // Update kartu stat "Alat Paling Boros" yang baru
                const mostWastefulStatValue = document.getElementById('most-wasteful-device-stat-value');
                if (data.most_wasteful_device && data.most_wasteful_device.device_name) {
                    mostWastefulStatValue.innerText = data.most_wasteful_device.device_name;
                } else {
                    mostWastefulStatValue.innerText = 'N/A'; // Atau pesan default jika tidak ada data
                }

                document.getElementById('cost-monthly-projection-value').innerText = 
                    'Rp ' + parseFloat(data.device_costs.projections.monthly.total || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

                // ========== INSIGHT & REKOMENDASI (Berbasis Data) ==========
                const insightContainer = document.getElementById('insight-container');
                let insightHTML = '';

                if (data.insights_recommendations && data.insights_recommendations.recommendations && data.insights_recommendations.recommendations.length > 0) {
                    // Tampilkan Potensi Penghematan Umum
                    const savings = data.insights_recommendations.overall_savings_potential;
                    if (savings && savings.max_rp > 0) {
                        insightHTML += `
                            <div class="insight-item success">
                                <i class="fas fa-money-bill-wave"></i>
                                <div class="text">
                                    <span>Potensi Penghematan Bulanan:</span>
                                    <small>Minimal Rp ${savings.min_rp.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})} hingga Rp ${savings.max_rp.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</small>
                                </div>
                            </div>
                        `;
                    }

                    // Iterasi rekomendasi
                    data.insights_recommendations.recommendations.forEach(rec => {
                        let iconClass = rec.icon || 'fas fa-lightbulb';
                        let savingsText = rec.estimated_savings_rp > 0 ? ` (Potensi Hemat: Rp ${rec.estimated_savings_rp.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})})` : '';
                        insightHTML += `
                            <div class="insight-item">
                                <i class="${iconClass}"></i>
                                <div class="text">
                                    <span>${rec.title}</span>
                                    <small>${rec.description}${savingsText}</small>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    insightHTML = `
                        <div class="insight-item">
                            <i class="fas fa-info-circle"></i>
                            <div class="text">
                                <span>Tidak Ada Insight/Rekomendasi</span>
                                <small>Sistem sedang memantau penggunaan energi Anda. Insight dan rekomendasi akan muncul di sini setelah terdeteksi.</small>
                            </div>
                        </div>
                    `;
                }
                insightContainer.innerHTML = insightHTML;

                // ========== BIAYA PER PERANGKAT ==========
                const deviceCostPeriodSelector = document.getElementById('device-cost-period-selector');
                const perDeviceCostsList = document.getElementById('per-device-costs-list');

                function renderPerDeviceCosts(period) {
                    let breakdownData = {};
                    let title = '';
                    let unit = 'Rp';

                    if (period === 'daily') {
                        breakdownData = data.device_costs.daily.breakdown;
                        title = 'Biaya Harian';
                    } else if (period === 'weekly_projection') {
                        breakdownData = data.device_costs.projections.weekly.breakdown;
                        title = 'Proyeksi Mingguan';
                    } else if (period === 'monthly_projection') {
                        breakdownData = data.device_costs.projections.monthly.breakdown;
                        title = 'Proyeksi Bulanan';
                    }

                    if (Object.keys(breakdownData).length > 0) {
                        let html = '';
                        for (const devId in breakdownData) {
                            const device = breakdownData[devId];
                            const cost = device.cost || 0;
                            html += `
                                <div class="device-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <div class="device-info">
                                        <div class="device-name">${device.name}</div>
                                        <div class="device-details">${title}</div>
                                    </div>
                                    <span class="badge bg-info">${unit} ${cost.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>
                                </div>
                            `;
                        }
                        perDeviceCostsList.innerHTML = html;
                    } else {
                        perDeviceCostsList.innerHTML = `
                            <div class="alert alert-info text-center py-2 mb-0" role="alert">
                                Tidak ada data biaya per perangkat untuk periode ini.
                            </div>
                        `;
                    }
                }

                // Initial render of per-device costs
                renderPerDeviceCosts(deviceCostPeriodSelector.value);

                // Add event listener for selector change (if not already added)
                if (!deviceCostPeriodSelector.hasAttribute('data-listener-added')) {
                    deviceCostPeriodSelector.addEventListener('change', () => {
                        renderPerDeviceCosts(deviceCostPeriodSelector.value);
                    });
                    deviceCostPeriodSelector.setAttribute('data-listener-added', 'true');
                }

                // ========== PERANGKAT TERBARU ==========
                const recentDevicesList = document.getElementById('recent-devices-list');
                if (data.recent_devices && data.recent_devices.length > 0) {
                    let html = '';
                    data.recent_devices.forEach(device => {
                        let statusText = 'Offline';
                        let statusClass = 'bg-danger';
                        let icon = 'fa-microchip';

                        if (device.is_truly_online == 1) {
                            if (device.machine_status === 'ON') {
                                statusText = 'Aktif';
                                statusClass = 'bg-success';
                                icon = 'fa-bolt';
                            } else if (device.machine_status === 'OFF') {
                                statusText = 'Idle';
                                statusClass = 'bg-warning text-dark';
                                icon = 'fa-pause';
                            } else {
                                statusText = 'Online';
                                statusClass = 'bg-primary';
                                icon = 'fa-microchip';
                            }
                        }

                        html += `
                            <div class="device-item">
                                <i class="fas ${icon}"></i>
                                <div class="device-info">
                                    <div class="device-name">
                                        <a href="devices/detail.php?id=${device.id}" class="text-decoration-none text-dark">
                                            ${device.device_name}
                                        </a>
                                    </div>
                                    <div class="device-details">${device.device_type || 'ESP32'}</div>
                                </div>
                                <span class="badge ${statusClass}">${statusText}</span>
                            </div>
                        `;
                    });
                    html += `
                        <div class="text-end mt-3">
                            <a href="devices/list.php" class="text-primary text-decoration-none small fw-bold">
                                Lihat Semua Perangkat <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    `;
                    recentDevicesList.innerHTML = html;
                } else {
                    recentDevicesList.innerHTML = `
                        <div class="alert alert-info text-center py-2 mb-0" role="alert">
                            Belum ada perangkat yang terdaftar.
                        </div>
                        <div class="text-end mt-3">
                            <a href="devices/add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i> Tambah Perangkat
                            </a>
                        </div>
                    `;
                }

            } else {
                console.error('Gagal mengambil ringkasan dashboard:', data.message);
                document.getElementById('insight-container').innerHTML = `
                    <div class="insight-item danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="text">
                            <span>Gagal Memuat Data</span>
                            <small>${data.message || 'Terjadi kesalahan saat memuat data dashboard.'}</small>
                        </div>
                    </div>
                `;
                // Error handling untuk kartu stat "Alat Paling Boros" yang baru
                const mostWastefulStatValue = document.getElementById('most-wasteful-device-stat-value');
                if (mostWastefulStatValue) {
                    mostWastefulStatValue.innerText = 'Error';
                }
                document.getElementById('per-device-costs-list').innerHTML = `
                    <div class="alert alert-danger text-center py-2 mb-0" role="alert">
                        Gagal memuat biaya per perangkat: ${data.message || 'Error koneksi.'}
                    </div>
                `;
                document.getElementById('recent-devices-list').innerHTML = `
                    <div class="alert alert-danger text-center py-2 mb-0" role="alert">
                        Gagal memuat perangkat terbaru: ${data.message || 'Error koneksi.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard summary:', error);
            document.getElementById('insight-container').innerHTML = `
                <div class="insight-item danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="text">
                        <span>Koneksi Error</span>
                        <small>Gagal terhubung ke server. Periksa koneksi internet Anda. (${error.message})</small>
                    </div>
                </div>
            `;
            // Error handling untuk kartu stat "Alat Paling Boros" yang baru
            const mostWastefulStatValue = document.getElementById('most-wasteful-device-stat-value');
            if (mostWastefulStatValue) {
                mostWastefulStatValue.innerText = 'Error';
            }
            document.getElementById('per-device-costs-list').innerHTML = `
                <div class="alert alert-danger text-center py-2 mb-0" role="alert">
                    Gagal memuat biaya per perangkat: ${error.message}.
                </div>
            `;
            document.getElementById('recent-devices-list').innerHTML = `
                <div class="alert alert-danger text-center py-2 mb-0" role="alert">
                    Gagal memuat perangkat terbaru: ${error.message}.
                </div>
            `;
        });
}


    // --- NEW: Navbar Alert Loading Functions ---
    function fetchNavbarAlerts() {
        if (!USER_ID) {
            console.error("USER_ID not defined for fetching navbar alerts.");
            return;
        }

        fetch(`${API_BASE_URL}get_unread_alerts.php?user_id=${USER_ID}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const unreadNavbarAlertsCount = document.getElementById('unread-navbar-alerts-count');
                    unreadNavbarAlertsCount.innerText = data.unread_count;
                    if (data.unread_count > 0) {
                         unreadNavbarAlertsCount.style.display = 'inline-block';
                    } else {
                         unreadNavbarAlertsCount.style.display = 'none';
                    }
                   
                    const alertsList = document.getElementById('recent-navbar-alerts-list');
                    alertsList.innerHTML = ''; // Bersihkan daftar sebelumnya

                    if (data.recent_alerts.length > 0) {
                        data.recent_alerts.forEach(alert => {
                            const alertItem = document.createElement('a');
                            alertItem.className = 'dropdown-item d-flex align-items-center';
                            // Link ke notification.php, yang akan menangani penandaan sebagai sudah dibaca jika alert_id ada di URL
                            alertItem.href = `settings/notification.php?alert_id=${alert.id}`;
                            // Tidak perlu onclick untuk mark as read di sini, biarkan notification.php yang menanganinya saat navigasi.

                            let iconClass = 'fas fa-exclamation-triangle text-warning'; // Default
                            if (alert.severity === 'error') {
                                iconClass = 'fas fa-exclamation-circle text-danger';
                            } else if (alert.severity === 'info') {
                                iconClass = 'fas fa-info-circle text-primary';
                            } else if (alert.severity === 'success') {
                                iconClass = 'fas fa-check-circle text-success';
                            }

                            alertItem.innerHTML = `
                                <div class="me-3">
                                    <div class="icon-circle bg-primary">
                                        <i class="${iconClass} text-white"></i>
                                    </div>
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
    // --- END NEW: Navbar Alert Loading Functions ---


    // --- NEW: Dashboard Alert Section Loading Functions ---
    async function loadDashboardAlerts() { // Renamed from loadAlerts to avoid confusion
        const alertsLoadingMessage = document.getElementById('alerts-loading-message');
        const noAlertsMessage = document.getElementById('no-alerts-message');
        const alertsListContainer = document.getElementById('alerts-list-container');
        const unreadBadge = document.getElementById('unread-alerts-badge');
        const viewAllLink = document.getElementById('view-all-alerts-link');
        
        if (alertsLoadingMessage) alertsLoadingMessage.style.display = 'block';
        if (noAlertsMessage) noAlertsMessage.style.display = 'none';
        if (alertsListContainer) alertsListContainer.innerHTML = '';
        if (unreadBadge) unreadBadge.style.display = 'none';
        if (viewAllLink) viewAllLink.style.display = 'none';

        try {
            // Memanggil API get_alerts.php yang baru dibuat
            const response = await fetch(`${API_BASE_URL}get_alerts.php?user_id=${USER_ID}&limit=5&unread_only=true`);
            const data = await response.json();

            if (alertsLoadingMessage) alertsLoadingMessage.style.display = 'none';

            if (data.status === 'success' && data.alerts.length > 0) {
                if (noAlertsMessage) noAlertsMessage.style.display = 'none';

                data.alerts.forEach(alert => {
                    const alertDiv = document.createElement('div');
                    alertDiv.classList.add('alert-item', alert.severity);
                    if (!alert.is_read) {
                        alertDiv.classList.add('unread');
                    }

                    let iconClass = 'fas fa-info-circle';
                    if (alert.severity === 'warning') iconClass = 'fas fa-exclamation-triangle';
                    if (alert.severity === 'error') iconClass = 'fas fa-exclamation-circle'; 

                    const deviceName = alert.device_name ? ` (${alert.device_name})` : '';

                    alertDiv.innerHTML = `
                        <span class="icon"><i class="${iconClass}"></i></span>
                        <span class="alert-message">${alert.message}${deviceName}</span>
                        <span class="alert-time">${new Date(alert.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })}</span>
                    `;
                    if (alertsListContainer) alertsListContainer.appendChild(alertDiv);
                });

                // Badge untuk dashboard utama (jumlah total unread alerts)
                if (unreadBadge && data.unread_count > 0) {
                    unreadBadge.innerText = data.unread_count + ' Baru';
                    unreadBadge.style.display = 'inline-block';
                }
                if (viewAllLink) viewAllLink.style.display = 'block';
            } else {
                if (noAlertsMessage) noAlertsMessage.style.display = 'block';
            }
        } catch (error) {
            console.error('Error fetching dashboard alerts:', error);
            if (alertsLoadingMessage) alertsLoadingMessage.style.display = 'none';
            if (alertsListContainer) alertsListContainer.innerHTML = `<div class="alert alert-danger text-center py-2 mb-0" role="alert">Gagal memuat peringatan: ${error.message}</div>`;
            if (noAlertsMessage) noAlertsMessage.style.display = 'none';
        }
    }
    // --- END NEW: Dashboard Alert Section Loading Functions ---


    // --- Consolidated DOMContentLoaded and Interval Calls ---
    document.addEventListener('DOMContentLoaded', function() {
        updateDashboardSummary();
        loadDashboardAlerts(); // Untuk bagian peringatan utama di dashboard
        fetchNavbarAlerts();    // Untuk dropdown notifikasi di navbar

        // Initial chart loads
        setTimeout(() => {
            loadRealtimeChart();
            loadDailyChart();
        }, 500);

        // Auto refresh intervals
        setInterval(updateDashboardSummary, 10000); // 10 detik
        setInterval(loadDashboardAlerts, 15000);    // 15 detik untuk peringatan dashboard utama
        setInterval(fetchNavbarAlerts, 30000);      // 30 detik untuk notifikasi navbar
        setInterval(loadRealtimeChart, 30000);      // 30 detik
        setInterval(loadDailyChart, 300000);        // 5 menit

        // Tab switching for charts
        const chartTab = document.getElementById('chartTab');
        if (chartTab) {
            chartTab.addEventListener('shown.bs.tab', function (event) {
                if (event.target.id === 'realtime-tab') {
                    loadRealtimeChart();
                } else if (event.target.id === 'harian-tab') {
                    loadDailyChart();
                }
            });
        }
    });
</script>

</body>
</html>
