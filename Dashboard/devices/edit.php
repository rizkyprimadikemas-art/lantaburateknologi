<?php
// File: dashboard/devices/edit.php
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

$device = null;
$device_db_id = $_GET['id'] ?? null;
$message = '';
$messageType = '';

if (!$device_db_id) {
    header('Location: list.php?error=no_device_id');
    exit();
}

try {
    $pdo = getPDOConnection();

    // Ambil data perangkat yang akan diedit
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
    $stmt->execute([$device_db_id, $userId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        header('Location: list.php?error=device_not_found');
        exit();
    }

    // Jika formulir disubmit (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $device_name = trim($_POST['device_name'] ?? '');
        // $device_type = trim($_POST['device_type'] ?? ''); // Dihapus untuk menyederhanakan
        $esp32_device_id = trim($_POST['esp32_device_id'] ?? '');
        $api_key = trim($_POST['api_key'] ?? '');
        $location = trim($_POST['location'] ?? '');
        // $description = trim($_POST['description'] ?? ''); // Dihapus untuk menyederhanakan
        $tarif_per_kwh = filter_var($_POST['tarif_per_kwh'] ?? 0, FILTER_VALIDATE_FLOAT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validasi input
        // Validasi disesuaikan karena device_type tidak lagi wajib di form
        if (empty($device_name) || empty($esp32_device_id) || empty($api_key)) {
            $message = 'Nama perangkat, ID Perangkat (ESP32), dan API Key tidak boleh kosong.';
            $messageType = 'danger';
        } elseif ($tarif_per_kwh === false || $tarif_per_kwh < 0) {
            $message = 'Tarif per kWh harus berupa angka positif.';
            $messageType = 'danger';
        } else {
            // Lakukan update ke database
            // Query UPDATE disesuaikan untuk tidak menyertakan device_type dan description
            $updateStmt = $pdo->prepare("
                UPDATE devices SET
                device_name = ?,
                device_id = ?,
                api_key = ?,
                location = ?,
                tarif_per_kwh = ?,
                is_active = ?,
                updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $success = $updateStmt->execute([
                $device_name,
                $esp32_device_id,
                $api_key,
                $location,
                $tarif_per_kwh,
                $is_active,
                $device_db_id,
                $userId
            ]);

            if ($success) {
                $message = 'Perangkat berhasil diperbarui!';
                $messageType = 'success';
                // Refresh data perangkat setelah update agar form menampilkan data terbaru
                $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
                $stmt->execute([$device_db_id, $userId]);
                $device = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = 'Gagal memperbarui perangkat. Silakan coba lagi.';
                $messageType = 'danger';
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error editing device: " . $e->getMessage());
    $message = 'Terjadi kesalahan database. Silakan coba lagi nanti.';
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Perangkat - <?= htmlspecialchars($device['device_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                            <a class="nav-link text-danger" href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid">
                <h1 class="mt-4 mb-4 fw-bold">Edit Perangkat</h1>
                <p class="lead text-muted mb-4">Ubah detail untuk perangkat: <?= htmlspecialchars($device['device_name']) ?></p>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">Formulir Edit Perangkat</div>
                    <div class="card-body">
                        <form action="edit.php?id=<?= htmlspecialchars($device_db_id) ?>" method="POST">
                            <div class="mb-3">
                                <label for="device_name" class="form-label">Nama Perangkat</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" value="<?= htmlspecialchars($device['device_name']) ?>" required>
                            </div>
                            <!-- Tipe Perangkat dihapus untuk menyederhanakan -->
                            <div class="mb-3">
                                <label for="esp32_device_id" class="form-label">ID Perangkat (ESP32)</label>
                                <input type="text" class="form-control" id="esp32_device_id" name="esp32_device_id" value="<?= htmlspecialchars($device['device_id']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key</label>
                                <input type="text" class="form-control" id="api_key" name="api_key" value="<?= htmlspecialchars($device['api_key']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lokasi</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($device['location']) ?>">
                            </div>
                            <!-- Deskripsi dihapus untuk menyederhanakan -->
                            <div class="mb-3">
                                <label for="tarif_per_kwh" class="form-label">Tarif per kWh (Rp)</label>
                                <input type="number" step="0.01" class="form-control" id="tarif_per_kwh" name="tarif_per_kwh" value="<?= htmlspecialchars($device['tarif_per_kwh']) ?>" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= $device['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                            <a href="detail.php?id=<?= htmlspecialchars($device_db_id) ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i> Batal</a>
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
