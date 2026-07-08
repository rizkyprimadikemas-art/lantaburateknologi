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
    if ($selectedDeviceDbId && $selectedDeviceDbId !== 'all') {
        $stmt = $pdo->prepare("SELECT id, device_name FROM devices WHERE id = ? AND user_id = ? AND is_active = 1");
        $stmt->execute([$selectedDeviceDbId, $userId]);
        $selectedDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$selectedDevice) { // Jika perangkat tidak ditemukan atau bukan milik user
            $selectedDeviceDbId = 'all'; // Kembali ke 'all'
        }
    } else {
        $selectedDeviceDbId = 'all'; // Default ke 'all' jika tidak ada atau 'all' di URL
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
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .summary-card {
            border: none;
            border-radius: 12px;
            padding: 20px;
            background: #f8f9fc;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .summary-card:hover {
            background: #eef2f7;
            transform: translateY(-2px);
        }
        .summary-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .summary-card .label {
            font-size: 0.8rem;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .change {
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }
        .summary-card .change.positive { color: #38a169; }
        .summary-card .change.negative { color: #e53e3e; }
        .summary-card .change.neutral { color: #718096; }

        .chart-container {
            position: relative;
            height: 350px; /* Tinggi standar untuk chart */
            width: 100%;
        }
        .chart-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .chart-controls .btn {
            flex-grow: 1;
        }
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
        .device-select-card {
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .device-select-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-weight: 600;
        }
        .device-select-card select {
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-weight: 500;
        }
        .daily-detail-table th, .daily-detail-table td {
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-badge .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-badge.online { background: #c6f6d5; color: #22543d; }
        .status-badge.online .dot { background: #38a169; }
        .status-badge.offline { background: #fed7d7; color: #742a2a; }
        .status-badge.offline .dot { background: #e53e3e; }
        .status-badge.active { background: #bee3f8; color: #2a4365; }
        .status-badge.active .dot { background: #3182ce; }
        .status-badge.idle { background: #fefcbf; color: #744210; }
        .status-badge.idle .dot { background: #d69e2e; }
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
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="../settings/profile.php">Profil Saya</a>
                                <a class="dropdown-item" href="../settings/notification.php">Pengaturan</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="../../auth/logout.php">Logout</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <h1 class="mt-4 mb-4 fw-bold">
                    <i class="fas fa-chart-pie me-2"></i>Analisis & Laporan
                </h1>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <!-- Device Selector -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card device-select-card">
                            <div class="card-header">
                                <i class="fas fa-microchip me-2"></i>Pilih Perangkat untuk Analisis
                            </div>
                            <div class="card-body">
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
                                    <div class="alert alert-info mb-0">
                                        Belum ada perangkat aktif. <a href="../devices/add.php" class="alert-link">Tambahkan sekarang</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="summary-card">
                            <div>
                                <div class="value" id="avg-power-value">0,00 W</div>
                                <div class="label">Rata-rata Daya</div>
                            </div>
                            <div class="change neutral" id="avg-power-change">
                                <i class="fas fa-minus"></i> 0%
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="summary-card">
                            <div>
                                <div class="value" id="total-energy-value">0,000 kWh</div>
                                <div class="label">Total Konsumsi</div>
                            </div>
                            <div class="change neutral" id="total-energy-change">
                                <i class="fas fa-minus"></i> 0%
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="summary-card">
                            <div>
                                <div class="value" id="total-cost-value">Rp 0</div>
                                <div class="label">Total Biaya</div>
                            </div>
                            <div class="change neutral" id="total-cost-change">
                                <i class="fas fa-minus"></i> 0%
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="summary-card">
                            <div>
                                <div class="value" id="avg-uptime-value">0%</div>
                                <div class="label">Uptime Rata-rata</div>
                            </div>
                            <div class="change neutral" id="avg-uptime-change">
                                <i class="fas fa-minus"></i> 0%
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
                                <div class="chart-controls">
                                    <button class="btn btn-outline-primary btn-sm active" data-period="daily" id="btn-daily">Harian</button>
                                    <button class="btn btn-outline-primary btn-sm" data-period="weekly" id="btn-weekly">Mingguan</button>
                                    <button class="btn btn-outline-primary btn-sm" data-period="monthly" id="btn-monthly">Bulanan</button>
                                </div>
                                <div id="energy-chart-message" class="text-center py-3">Pilih perangkat atau tunggu data dimuat.</div>
                                <div class="chart-container">
                                    <canvas id="energyConsumptionChart" style="display: none;"></canvas>
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
                                <div id="distribution-chart-message" class="text-center py-3">Pilih perangkat atau tunggu data dimuat.</div>
                                <div class="chart-container">
                                    <canvas id="deviceDistributionChart" style="display: none;"></canvas>
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
                        <div id="daily-details-message" class="alert alert-info text-center py-2">Pilih perangkat atau tunggu data dimuat.</div>
                        <div class="table-responsive">
                            <table class="table table-hover daily-detail-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Perangkat</th>
                                        <th>Konsumsi (kWh)</th>
                                        <th>Biaya (Rp)</th>
                                        <th>Status Mesin</th>
                                    </tr>
                                </thead>
                                <tbody id="daily-details-tbody">
                                    <!-- Data akan diisi oleh JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let selectedDeviceId = <?= json_encode($selectedDeviceDbId) ?>;
        const USER_ID = <?= json_encode($userId) ?>;
        const API_BASE_URL = '../../api/get_analytics_data.php'; // API tunggal untuk semua data analitik

        let energyChartInstance = null;
        let distributionChartInstance = null;
        let currentEnergyChartPeriod = 'daily'; // Default period for energy chart

        // Helper function to format currency
        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Helper function to update change percentage display
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

        // --- Reset Display Functions ---
        function resetSummaryStats() {
            document.getElementById('avg-power-value').innerText = '0,00 W';
            document.getElementById('total-energy-value').innerText = '0,000 kWh';
            document.getElementById('total-cost-value').innerText = 'Rp 0';
            document.getElementById('avg-uptime-value').innerText = '0%';
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
            document.getElementById('energyConsumptionChart').style.display = 'none';
            document.getElementById('energy-chart-message').innerHTML = '<div class="alert alert-info text-center py-2 mb-0" role="alert">Pilih perangkat atau tunggu data dimuat.</div>';
        }

        function resetDistributionChart() {
            if (distributionChartInstance) {
                distributionChartInstance.destroy();
                distributionChartInstance = null;
            }
            document.getElementById('deviceDistributionChart').style.display = 'none';
            document.getElementById('distribution-chart-message').innerHTML = '<div class="alert alert-info text-center py-2 mb-0" role="alert">Pilih perangkat atau tunggu data dimuat.</div>';
        }

        function resetDailyDetails() {
            document.getElementById('daily-details-tbody').innerHTML = '';
            document.getElementById('daily-details-message').innerHTML = '<div class="alert alert-info text-center py-2 mb-0" role="alert">Pilih perangkat atau tunggu data dimuat.</div>';
        }

        function resetAllDisplay() {
            resetSummaryStats();
            resetEnergyChart();
            resetDistributionChart();
            resetDailyDetails();
        }


        // --- Data Loading Functions ---
        async function updateSummaryStats() {
            if (!selectedDeviceId) {
                resetSummaryStats();
                return;
            }
            try {
                const response = await fetch(`${API_BASE_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=summary`);
                const data = await response.json();

                if (data.status === 'success' && data.summary_stats) {
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
            } catch (error) {
                console.error('Error fetching summary stats:', error);
                resetSummaryStats();
            }
        }

        async function loadEnergyConsumptionChart(period) {
            if (!selectedDeviceId) {
                resetEnergyChart();
                return;
            }

            const canvas = document.getElementById('energyConsumptionChart');
            const messageEl = document.getElementById('energy-chart-message');
            messageEl.innerHTML = ''; // Clear previous messages
            canvas.style.display = 'block'; // Show canvas

            try {
                const response = await fetch(`${API_BASE_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=energy_chart&period=${period}`);
                const data = await response.json();

                if (data.status === 'success' && data.energy_chart_data && data.energy_chart_data.labels.length > 0) {
                    if (energyChartInstance) {
                        energyChartInstance.destroy();
                    }
                    const ctx = canvas.getContext('2d');
                    energyChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.energy_chart_data.labels,
                            datasets: [{
                                label: 'Konsumsi Energi (kWh)',
                                tension: 0.3,
                                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                                borderColor: 'rgba(102, 126, 234, 1)',
                                pointRadius: 3,
                                pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                                pointBorderColor: 'rgba(255,255,255,0.8)',
                                pointHoverRadius: 5,
                                data: data.energy_chart_data.data,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'white',
                                    bodyColor: '#2d3748',
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    displayColors: false,
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
                                    ticks: { maxTicksLimit: 10, font: { size: 10 } }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        maxTicksLimit: 5,
                                        callback: function(value) {
                                            return value + ' kWh';
                                        }
                                    },
                                    grid: { color: 'rgba(0,0,0,0.05)' }
                                }
                            }
                        }
                    });
                } else {
                    canvas.style.display = 'none';
                    messageEl.innerHTML = `<div class="alert alert-info text-center py-2 mb-0" role="alert">${data.message || 'Belum ada data konsumsi energi untuk periode ini.'}</div>`;
                    if (energyChartInstance) {
                        energyChartInstance.destroy();
                        energyChartInstance = null;
                    }
                }
            } catch (error) {
                console.error('Error fetching energy consumption chart data:', error);
                canvas.style.display = 'none';
                messageEl.innerHTML = `<div class="alert alert-danger text-center py-2 mb-0" role="alert">Gagal memuat grafik konsumsi energi: ${error.message}</div>`;
                if (energyChartInstance) {
                    energyChartInstance.destroy();
                    energyChartInstance = null;
                }
            }
        }

        async function loadDeviceDistributionChart() {
            if (!selectedDeviceId) {
                resetDistributionChart();
                return;
            }

            const canvas = document.getElementById('deviceDistributionChart');
            const messageEl = document.getElementById('distribution-chart-message');
            messageEl.innerHTML = ''; // Clear previous messages
            canvas.style.display = 'block'; // Show canvas

            try {
                const response = await fetch(`${API_BASE_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=device_distribution`);
                const data = await response.json();

                if (data.status === 'success' && data.device_distribution_data && data.device_distribution_data.labels.length > 0) {
                    if (distributionChartInstance) {
                        distributionChartInstance.destroy();
                    }
                    const ctx = canvas.getContext('2d');
                    distributionChartInstance = new Chart(ctx, {
                        type: 'doughnut', // Atau 'pie'
                        data: {
                            labels: data.device_distribution_data.labels,
                            datasets: [{
                                data: data.device_distribution_data.data,
                                backgroundColor: [
                                    '#667eea', '#764ba2', '#a3aed0', '#f6ad55', '#fc8181', '#4fd1c5', '#63b3ed'
                                ],
                                hoverBackgroundColor: [
                                    '#5a67d8', '#6b46c1', '#8b94b3', '#ed8936', '#e53e3e', '#38b2ac', '#4299e1'
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed !== null) {
                                                label += context.parsed;
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    canvas.style.display = 'none';
                    messageEl.innerHTML = `<div class="alert alert-info text-center py-2 mb-0" role="alert">${data.message || 'Belum ada data distribusi perangkat.'}</div>`;
                    if (distributionChartInstance) {
                        distributionChartInstance.destroy();
                        distributionChartInstance = null;
                    }
                }
            } catch (error) {
                console.error('Error fetching device distribution chart data:', error);
                canvas.style.display = 'none';
                messageEl.innerHTML = `<div class="alert alert-danger text-center py-2 mb-0" role="alert">Gagal memuat grafik distribusi perangkat: ${error.message}</div>`;
                if (distributionChartInstance) {
                    distributionChartInstance.destroy();
                    distributionChartInstance = null;
                }
            }
        }

        async function loadDailyConsumptionDetails() {
            if (!selectedDeviceId) {
                resetDailyDetails();
                return;
            }

            const tbody = document.getElementById('daily-details-tbody');
            const messageEl = document.getElementById('daily-details-message');
            tbody.innerHTML = ''; // Clear previous data
            messageEl.innerHTML = ''; // Clear previous message

            try {
                const response = await fetch(`${API_BASE_URL}?user_id=${USER_ID}&device_id=${selectedDeviceId}&type=daily_details`);
                const data = await response.json();

                if (data.status === 'success' && data.daily_consumption_details && data.daily_consumption_details.length > 0) {
                    data.daily_consumption_details.forEach(detail => {
                        const row = tbody.insertRow();
                        row.insertCell().innerText = detail.date;
                        row.insertCell().innerText = detail.device_name;
                        row.insertCell().innerText = `${(detail.energy_kwh || 0).toFixed(3).replace('.', ',')} kWh`;
                        row.insertCell().innerText = formatRupiah(detail.cost_rp || 0);

                        const statusCell = row.insertCell();
                        const machineStatus = detail.machine_status || 'OFF';
                        let badgeClass = 'idle'; // Default
                        if (machineStatus === 'ON') {
                            badgeClass = 'active';
                        } else if (machineStatus === 'OFF') {
                            badgeClass = 'idle';
                        }
                        statusCell.innerHTML = `<span class="status-badge ${badgeClass}"><span class="dot"></span> ${machineStatus}</span>`;
                    });
                } else {
                    messageEl.innerHTML = `<div class="alert alert-info text-center py-2 mb-0" role="alert">${data.message || 'Belum ada detail konsumsi harian untuk perangkat ini.'}</div>`;
                }
            } catch (error) {
                console.error('Error fetching daily consumption details:', error);
                messageEl.innerHTML = `<div class="alert alert-danger text-center py-2 mb-0" role="alert">Gagal memuat detail konsumsi harian: ${error.message}</div>`;
            }
        }

        // --- Event Handlers ---
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

            if (selectedDeviceId) {
                updateSummaryStats();
                loadEnergyConsumptionChart(currentEnergyChartPeriod); // Reload chart with current period
                loadDeviceDistributionChart();
                loadDailyConsumptionDetails();
            } else {
                resetAllDisplay();
            }
        }

        function handleEnergyChartPeriodChange(event) {
            document.querySelectorAll('.chart-controls .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            currentEnergyChartPeriod = event.target.dataset.period;
            loadEnergyConsumptionChart(currentEnergyChartPeriod);
        }

        // --- Initial Load and Intervals ---
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.getElementById('menu-toggle').addEventListener('click', function() {
                document.getElementById('wrapper').classList.toggle('toggled');
            });

            // Set up event listener for device selector
            const deviceSelector = document.getElementById('device-selector');
            if (deviceSelector) {
                deviceSelector.addEventListener('change', handleDeviceChange);
            }

            // Set up event listeners for energy chart period buttons
            document.getElementById('btn-daily').addEventListener('click', handleEnergyChartPeriodChange);
            document.getElementById('btn-weekly').addEventListener('click', handleEnergyChartPeriodChange);
            document.getElementById('btn-monthly').addEventListener('click', handleEnergyChartPeriodChange);

            // Initial load
            if (selectedDeviceId) {
                updateSummaryStats();
                loadEnergyConsumptionChart(currentEnergyChartPeriod);
                loadDeviceDistributionChart();
                loadDailyConsumptionDetails();
            } else {
                resetAllDisplay();
            }

            // Set intervals for periodic updates
            setInterval(updateSummaryStats, 15000); // Update summary every 15 seconds
            setInterval(() => loadEnergyConsumptionChart(currentEnergyChartPeriod), 30000); // Update energy chart every 30 seconds
            setInterval(loadDeviceDistributionChart, 60000); // Update distribution chart every 60 seconds
            setInterval(loadDailyConsumptionDetails, 30000); // Update daily details every 30 seconds
        });
    </script>
</body>
</html>
