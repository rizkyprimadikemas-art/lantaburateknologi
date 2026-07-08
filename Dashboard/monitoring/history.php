<?php
// File: dashboard/monitoring/history.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin(); // Memastikan pengguna sudah login

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$userName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Pengguna';
$userEmail = $currentUser['email'] ?? 'email@example.com';

$allDevices = [];
$selectedDeviceDbId = $_GET['device_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default awal bulan ini
$endDate = $_GET['end_date'] ?? date('Y-m-d');     // Default hari ini

$historyData = [];
$limit = 20; // Jumlah data per halaman
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page); // Pastikan page minimal 1
$offset = ($page - 1) * $limit;
$totalRecords = 0;

// --- Logika Penentuan Tabel Berdasarkan Rentang Tanggal ---
$startDateObj = new DateTime($startDate);
$endDateObj = new DateTime($endDate);
$interval = $startDateObj->diff($endDateObj);
$diffDays = $interval->days;

$tableName = 'energy_data';
$selectColumns = 'created_at, power, voltage, current, energy, machine_status, device_status';
$timeColumn = 'created_at';
$orderByColumn = 'created_at';
$displayType = 'raw'; // 'raw', 'hourly', 'daily'

if ($diffDays > 7 && $diffDays <= 90) { // Lebih dari 7 hari, sampai 3 bulan (sekitar 90 hari)
    $tableName = 'energy_data_hourly';
    // Menggunakan alias agar nama kolom konsisten dengan tabel mentah
    // Hanya memilih kolom yang ada di energy_data_hourly
    $selectColumns = 'timestamp AS created_at, avg_power_w AS power, total_energy_kwh AS energy';
    $timeColumn = 'timestamp';
    $orderByColumn = 'timestamp';
    $displayType = 'hourly';
} elseif ($diffDays > 90 && $diffDays <= 1825) { // Lebih dari 3 bulan, sampai 5 tahun (sekitar 1825 hari)
    $tableName = 'energy_data_daily';
    // Menggunakan alias agar nama kolom konsisten dengan tabel mentah
    // Hanya memilih kolom yang ada di energy_data_daily
    // Asumsi: kolom rata-rata daya di daily adalah avg_power (bukan avg_power_w)
    $selectColumns = 'date AS created_at, avg_power AS power, total_energy_kwh AS energy';
    $timeColumn = 'date';
    $orderByColumn = 'date';
    $displayType = 'daily';
}
// --- Akhir Logika Penentuan Tabel ---

try {
    $pdo = getPDOConnection();

    // Ambil semua perangkat milik pengguna
    $stmt = $pdo->prepare("SELECT id, device_name, device_id FROM devices WHERE user_id = ? AND is_active = 1 ORDER BY device_name ASC");
    $stmt->execute([$userId]);
    $allDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Jika tidak ada perangkat yang dipilih dari URL, pilih perangkat pertama
    if (!$selectedDeviceDbId && !empty($allDevices)) {
        $selectedDeviceDbId = $allDevices[0]['id'];
    }

    if ($selectedDeviceDbId) {
        // Ambil data historis dari tabel yang ditentukan
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS {$selectColumns}
            FROM {$tableName}
            WHERE device_id = ? AND {$timeColumn} BETWEEN ? AND ?
            ORDER BY {$orderByColumn} DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $pdo->prepare($sql);
        
        // Sesuaikan parameter tanggal untuk klausa BETWEEN
        $paramStartDate = $startDate . ' 00:00:00';
        $paramEndDate = $endDate . ' 23:59:59';

        if ($displayType === 'daily') {
            $paramStartDate = $startDate; // Untuk kolom 'date' yang hanya tanggal
            $paramEndDate = $endDate;     // Untuk kolom 'date' yang hanya tanggal
        }

        $stmt->execute([$selectedDeviceDbId, $paramStartDate, $paramEndDate, $limit, $offset]);
        $historyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil total records untuk pagination
        $totalRecords = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    }

} catch (PDOException $e) {
    error_log("Error fetching historical data: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan database saat memuat data historis.';
}

$totalPages = ceil($totalRecords / $limit);
$page = min($page, $totalPages > 0 ? $totalPages : 1); // Pastikan page tidak melebihi totalPages

// Logika untuk menampilkan rentang halaman
$maxPagesToShow = 5; // Jumlah maksimal halaman yang ditampilkan (termasuk halaman saat ini)
$halfMaxPages = floor($maxPagesToShow / 2);

$startPage = max(1, $page - $halfMaxPages);
$endPage = min($totalPages, $page + $halfMaxPages);

// Penyesuaian jika rentang terlalu dekat ke awal atau akhir
if ($endPage - $startPage + 1 < $maxPagesToShow) {
    if ($startPage == 1) {
        $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
    } elseif ($endPage == $totalPages) {
        $startPage = max(1, $endPage - $maxPagesToShow + 1);
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Historis - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom CSS untuk tabel historis agar lebih ringkas */
        .table-history th, .table-history td {
            font-size: 0.85rem; /* Ukuran font lebih kecil */
            padding: 0.5rem 0.75rem; /* Padding lebih kecil */
            white-space: nowrap; /* Mencegah teks wrapping */
        }
        .table-history .badge {
            font-size: 0.75rem; /* Ukuran badge lebih kecil */
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
                <a href="history.php" class="list-group-item list-group-item-action active">
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
                            <a class="nav-link text-danger" href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <h1 class="mt-4 mb-4 fw-bold">Data Historis</h1>
                <p class="lead text-muted mb-4">Lihat riwayat data sensor perangkat Anda.</p>

                <div class="card mb-4">
                    <div class="card-header">Filter Data</div>
                    <div class="card-body">
                        <form method="GET" action="history.php">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="device-selector" class="form-label">Pilih Perangkat</label>
                                    <?php if (!empty($allDevices)): ?>
                                        <select class="form-select" id="device-selector" name="device_id">
                                            <?php foreach ($allDevices as $deviceOption): ?>
                                                <option value="<?= $deviceOption['id'] ?>" <?= ($deviceOption['id'] == $selectedDeviceDbId) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($deviceOption['device_name']) ?> (<?= htmlspecialchars($deviceOption['device_id']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0" role="alert">
                                            Anda belum memiliki perangkat yang terdaftar.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Dari Tanggal</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selectedDeviceDbId): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        Data Historis (<?= htmlspecialchars($startDate) ?> - <?= htmlspecialchars($endDate) ?>)
                        <?php if ($displayType === 'hourly'): ?>
                            <span class="badge bg-info ms-2">Data Agregasi Per Jam</span>
                        <?php elseif ($displayType === 'daily'): ?>
                            <span class="badge bg-info ms-2">Data Agregasi Harian</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($historyData)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped table-history"> <!-- Menambahkan class table-history -->
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Daya (W)</th>
                                            <?php if ($displayType === 'raw'): // Voltage dan Arus hanya ada di data mentah ?>
                                                <th>Tegangan (V)</th>
                                                <th>Arus (A)</th>
                                            <?php endif; ?>
                                            <th>Energi (kWh)</th>
                                            <?php if ($displayType === 'raw'): // Kolom status hanya ada di data mentah ?>
                                                <th>Status Mesin</th>
                                                <th>Status ESP32</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historyData as $data): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                        // Note: 'created_at' adalah alias yang digunakan di selectColumns untuk timestamp/date
                                                        echo htmlspecialchars($data['created_at']);
                                                    ?>
                                                </td>
                                                <td><?= number_format($data['power'], 2, ',', '.') ?></td>
                                                <?php if ($displayType === 'raw'): ?>
                                                    <td><?= number_format($data['voltage'], 2, ',', '.') ?></td>
                                                    <td><?= number_format($data['current'], 2, ',', '.') ?></td>
                                                <?php endif; ?>
                                                <td><?= number_format($data['energy'], 3, ',', '.') ?></td>
                                                <?php if ($displayType === 'raw'): ?>
                                                    <td>
                                                        <span class="badge <?= $data['machine_status'] == 'ON' ? 'bg-success' : 'bg-danger' ?>">
                                                            <?= htmlspecialchars($data['machine_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $data['device_status'] == 'online' ? 'bg-primary' : 'bg-danger' ?>">
                                                            <?= htmlspecialchars($data['device_status']) ?>
                                                        </span>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?device_id=<?= $selectedDeviceDbId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $page - 1 ?>">Previous</a>
                                    </li>

                                    <?php if ($startPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?device_id=<?= $selectedDeviceDbId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=1">1</a>
                                        </li>
                                        <?php if ($startPage > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?device_id=<?= $selectedDeviceDbId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?device_id=<?= $selectedDeviceDbId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a>
                                        </li>
                                    <?php endif; ?>

                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?device_id=<?= $selectedDeviceDbId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $page + 1 ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>

                        <?php else: ?>
                            <div class="alert alert-info text-center" role="alert">
                                Tidak ada data historis yang ditemukan untuk periode ini.
                                <?php if ($displayType === 'hourly'): ?>
                                    (Data agregasi per jam)
                                <?php elseif ($displayType === 'daily'): ?>
                                    (Data agregasi harian)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        Pilih perangkat dari daftar di atas untuk melihat data historis.
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });
    </script>
</body>
</html>
