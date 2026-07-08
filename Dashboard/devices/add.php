<?php
// File: dashboard/devices/add.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$deviceName = '';
$macAddress = '';
$deviceType = ''; // Default value
$location = '';
$description = '';
$powerThreshold = 10.00; // Default value
$tarifPerKwh = 1500.00; // Default value (variabel diubah menjadi $tarifPerKwh)

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceName = trim($_POST['device_name'] ?? '');
    $macAddress = trim(strtoupper($_POST['mac_address'] ?? '')); // Pastikan MAC Address uppercase
    $deviceType = trim($_POST['device_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $powerThreshold = floatval($_POST['power_threshold'] ?? 10.00);
    $tarifPerKwh = floatval($_POST['tarif_per_kwh'] ?? 1500.00); // Mengambil dari $_POST['tarif_per_kwh']

    // Validasi input
    if (empty($deviceName)) {
        $error = 'Nama Perangkat wajib diisi.';
    } elseif (empty($macAddress)) {
        $error = 'MAC Address ESP32 wajib diisi.';
    } elseif (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $macAddress)) {
        $error = 'Format MAC Address tidak valid.';
    } else {
        try {
            $pdo = getPDOConnection();

            // --- Logika Pengecekan dan Klaim/Tambah Perangkat ---

            // 1. Cek apakah MAC Address sudah ada di database
            $stmt = $pdo->prepare("SELECT id, user_id, is_active FROM devices WHERE device_id = ?");
            $stmt->execute([$macAddress]);
            $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingDevice) {
                // Perangkat dengan MAC Address ini sudah ada
                if ($existingDevice['user_id'] === NULL && $existingDevice['is_active'] == 0) {
                    // Ini adalah perangkat yang didaftarkan secara otomatis dan belum diklaim
                    // Lakukan UPDATE untuk mengklaim perangkat ini
                    $updateStmt = $pdo->prepare("UPDATE devices SET 
                                                    user_id = ?, 
                                                    device_name = ?, 
                                                    device_type = ?, 
                                                    location = ?, 
                                                    description = ?, 
                                                    power_threshold = ?, 
                                                    tarif_per_kwh = ?,  
                                                    is_active = 1, 
                                                    updated_at = NOW() 
                                                WHERE id = ?");
                    $updateStmt->execute([
                        $userId,
                        $deviceName,
                        $deviceType,
                        $location,
                        $description,
                        $powerThreshold,
                        $tarifPerKwh, // Menggunakan variabel $tarifPerKwh
                        $existingDevice['id']
                    ]);
                    $success = 'Perangkat berhasil diklaim dan diaktifkan!';
                    header('Location: list.php?success=' . urlencode($success));
                    exit();

                } else {
                    // Perangkat sudah terdaftar dan diklaim oleh pengguna lain atau sudah aktif
                    $error = 'MAC Address ini sudah terdaftar untuk perangkat lain.';
                }
            } else {
                // MAC Address belum ada di database, tambahkan sebagai perangkat baru
                $newApiKey = bin2hex(random_bytes(32)); // Generate API Key baru untuk perangkat manual
                $insertStmt = $pdo->prepare("INSERT INTO devices (user_id, device_name, device_id, device_type, location, description, power_threshold, tarif_per_kwh, api_key, is_active, is_online, created_at, updated_at) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())");
                $insertStmt->execute([
                    $userId,
                    $deviceName,
                    $macAddress,
                    $deviceType,
                    $location,
                    $description,
                    $powerThreshold,
                    $tarifPerKwh, // Menggunakan variabel $tarifPerKwh
                    $newApiKey
                ]);
                $success = 'Perangkat baru berhasil ditambahkan!';
                header('Location: list.php?success=' . urlencode($success));
                exit();
            }

        } catch (PDOException $e) {
            error_log("Database error adding/claiming device: " . $e->getMessage());
            $error = 'Terjadi kesalahan database: ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error adding/claiming device: " . $e->getMessage());
            $error = 'Terjadi kesalahan server.';
        }
    }
}

// HTML Form
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Perangkat Baru - Lantabur Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-bolt"></i> Lantabur
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
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($currentUser['email'] ?? 'Pengguna') ?>
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
                <h1 class="mt-4 mb-4 fw-bold">Tambah Perangkat Baru</h1>
                <p class="lead text-muted mb-4">Daftarkan perangkat monitoring energi Anda dengan MAC Address.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        Informasi Perangkat
                    </div>
                    <div class="card-body">
                        <form method="POST" action="add.php">
                            <div class="mb-3">
                                <label for="device_name" class="form-label">Nama Perangkat</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" value="<?= htmlspecialchars($deviceName) ?>" placeholder="Berikan nama yang mudah dikenali untuk perangkat Anda." required>
                            </div>
                            <div class="mb-3">
                                <label for="mac_address" class="form-label">MAC Address ESP32</label>
                                <input type="text" class="form-control" id="mac_address" name="mac_address" value="<?= htmlspecialchars($macAddress) ?>" placeholder="Masukkan MAC Address unik dari modul ESP32 Anda. Anda bisa mendapatkannya dengan menjalankan sketch sederhana yang mencetak `WiFi.macAddress()` ke Serial Monitor." required pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$" title="Format MAC Address: XX:XX:XX:XX:XX:XX">
                            </div>
                            <div class="mb-3">
                                <label for="device_type" class="form-label">Tipe Perangkat</label>
                                <select class="form-select" id="device_type" name="device_type" required>
                                    <option value="PZEM-004T" <?= ($deviceType == 'PZEM-004T') ? 'selected' : '' ?>>PZEM-004T</option>
                                    <option value="Smart Plug" <?= ($deviceType == 'Smart Plug') ? 'selected' : '' ?>>Smart Plug</option>
                                    <option value="Other" <?= ($deviceType == 'Other') ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lokasi</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="Contoh: Ruang Tamu, Dapur">
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Deskripsi singkat tentang perangkat ini."><?= htmlspecialchars($description) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="power_threshold" class="form-label">Ambang Batas Daya (Watt)</label>
                                <input type="number" step="0.01" class="form-control" id="power_threshold" name="power_threshold" value="<?= htmlspecialchars($powerThreshold) ?>" required>
                                <small class="form-text text-muted">Daya minimum untuk dianggap aktif.</small>
                            </div>
                            <div class="mb-3">
                                <label for="tarif_per_kwh" class="form-label">Tarif per kWh (Rp)</label>
                                <input type="number" step="0.01" class="form-control" id="tarif_per_kwh" name="tarif_per_kwh" value="<?= htmlspecialchars($tarifPerKwh) ?>" required>
                                <small class="form-text text-muted">Biaya per kWh untuk perhitungan estimasi.</small>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Daftarkan Perangkat</button>
                            <a href="list.php" class="btn btn-secondary">Kembali ke Daftar Perangkat</a>
                        </form>
                    </div>
                </div>
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
