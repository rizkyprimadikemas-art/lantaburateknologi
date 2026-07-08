<?php
// File: dashboard/settings/profile.php
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

$message = '';
$messageType = ''; // 'success' or 'danger'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

    try {
        $pdo = getPDOConnection();

        // Validate current password if new password is provided
        if (!empty($newPassword) || !empty($currentPassword)) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $message = 'Kata sandi saat ini salah.';
                $messageType = 'danger';
            }
        }

        // Validate new password
        if (empty($message) && !empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $message = 'Kata sandi baru harus minimal 6 karakter.';
                $messageType = 'danger';
            } elseif ($newPassword !== $confirmNewPassword) {
                $message = 'Konfirmasi kata sandi baru tidak cocok.';
                $messageType = 'danger';
            }
        }

        // Validate email uniqueness (if changed)
        if (empty($message) && $email !== $userEmail) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $message = 'Email ini sudah digunakan oleh pengguna lain.';
                $messageType = 'danger';
            }
        }

        // If no errors, proceed with update
        if (empty($message)) {
            $updateFields = [];
            $updateValues = [];

            if ($fullName !== ($currentUser['full_name'] ?? '')) {
                $updateFields[] = 'full_name = ?';
                $updateValues[] = $fullName;
            }
            if ($email !== $userEmail) {
                $updateFields[] = 'email = ?';
                $updateValues[] = $email;
            }
            if (!empty($newPassword)) {
                $updateFields[] = 'password = ?';
                $updateValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if (!empty($updateFields)) {
                $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $updateValues[] = $userId;

                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute($updateValues);

                // Update session data
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['email'] = $email;
                // Don't update password in session for security reasons

                $message = 'Profil berhasil diperbarui.';
                $messageType = 'success';

                // Refresh current user data after update
                $currentUser = getCurrentUser();
                $userName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Pengguna';
                $userEmail = $currentUser['email'] ?? 'email@example.com';

            } else {
                $message = 'Tidak ada perubahan yang terdeteksi.';
                $messageType = 'info'; // Use info for no changes
            }
        }

    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $message = 'Terjadi kesalahan database saat memperbarui profil. Silakan coba lagi.';
        $messageType = 'danger';
    }
}

// Fetch total devices and online devices for sidebar/navbar
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
    <title>Pengaturan Akun - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== GLOBAL STYLE (konsisten dengan detail.php) ===== */
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fc;
        }
        #wrapper {
            display: flex;
        }
        #sidebar-wrapper {
            min-width: 250px;
            white-space: nowrap;
            overflow-x: hidden;
            background-color: #343a40;
            color: #fff;
            transition: margin 0.25s ease-out;
        }
        #sidebar-wrapper .sidebar-heading {
            padding: 20px 15px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        #sidebar-wrapper .list-group {
            width: 100%;
        }
        #sidebar-wrapper .list-group-item {
            background-color: transparent;
            color: #adb5bd;
            border: none;
            padding: 15px 20px;
            transition: all 0.3s ease;
        }
        #sidebar-wrapper .list-group-item:hover,
        #sidebar-wrapper .list-group-item.active {
            background-color: #495057;
            color: #fff;
        }
        #sidebar-wrapper .list-group-item i {
            margin-right: 10px;
        }
        #page-content-wrapper {
            flex-grow: 1;
            padding: 20px;
            background-color: #f8f9fc;
            min-width: 0;
        }
        .navbar {
            background-color: #fff;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
            padding: 10px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .navbar .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .navbar .nav-link:hover {
            color: #667eea;
        }
        .navbar .dropdown-menu {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .navbar .dropdown-item {
            font-size: 0.9rem;
        }
        .navbar .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .toggle-button {
            color: #495057;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            background: #fff;
        }
        .toggle-button:hover {
            background-color: #f0f0f0;
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

        /* ===== PROFILE HEADER (gradient seperti device-header) ===== */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 28px 32px;
            color: white;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .profile-header .avatar-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.4);
        }
        .profile-header .profile-info h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .profile-header .profile-info .profile-meta {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .profile-header .profile-info .profile-meta i {
            margin-right: 4px;
        }

        /* ===== CARD UTAMA ===== */
        .card-profile {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            background: #fff;
            margin-bottom: 24px;
        }
        .card-profile .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 16px 24px;
            color: #2d3748;
        }
        .card-profile .card-header i {
            margin-right: 8px;
            color: #667eea;
        }
        .card-profile .card-body {
            padding: 24px;
        }

        /* ===== FORM STYLING ===== */
        .form-section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title i {
            color: #667eea;
        }
        .form-profile .form-label {
            font-weight: 500;
            color: #4a5568;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .form-profile .form-control {
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: #fafbfc;
        }
        .form-profile .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            background: #fff;
        }
        .form-profile .form-text {
            font-size: 0.75rem;
            color: #a0aec0;
            margin-top: 4px;
        }
        .form-profile .input-group-text {
            border-radius: 8px;
            background: #f1f3f5;
            border: 1.5px solid #e2e8f0;
            color: #718096;
        }

        /* ===== BUTTON ===== */
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #fff;
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            color: #fff;
        }
        .btn-save:active {
            transform: translateY(0);
        }
        .btn-save i {
            margin-right: 8px;
        }

        /* ===== ALERT ===== */
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 14px 20px;
            font-size: 0.85rem;
        }
        .alert-custom.alert-success {
            background: #c6f6d5;
            color: #22543d;
        }
        .alert-custom.alert-danger {
            background: #fed7d7;
            color: #742a2a;
        }
        .alert-custom.alert-info {
            background: #bee3f8;
            color: #2a4365;
        }

        /* ===== SEPARATOR ===== */
        .divider-custom {
            border: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 24px 0;
        }

        /* ===== PASSWORD SECTION CARD ===== */
        .password-section {
            background: #fafbfc;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #eef2f7;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .profile-header {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }
            .profile-header .profile-info h1 {
                font-size: 1.2rem;
            }
            .card-profile .card-body {
                padding: 16px;
            }
            #page-content-wrapper {
                padding: 12px;
            }
        }

        /* ===== SIDEBAR TOGGLE ===== */
        #wrapper.toggled #sidebar-wrapper {
            margin-left: -250px;
        }
        @media (min-width: 768px) {
            #sidebar-wrapper {
                margin-left: 0;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: -250px;
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
                <a href="../analytics/overview.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-pie"></i> Analisis & Laporan
                </a>
                <a href="notification.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-bell"></i> Notifikasi
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action active">
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
                            <a class="nav-link" href="#" id="sync-button"><i class="fas fa-sync-alt"></i> Sync</a>
                        </li>
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
                                    <a class="dropdown-item text-center small text-gray-500 py-2" href="#">Memuat peringatan...</a>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500 py-2" href="notification.php">Lihat Semua Peringatan</a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="profile.php">Profil Saya</a>
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
                <!-- Back link -->
                <a href="../index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>

                <!-- Profile Header (gradient seperti device-header) -->
                <div class="profile-header">
                    <div class="avatar-wrapper">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <h1><?= htmlspecialchars($userName) ?></h1>
                        <div class="profile-meta">
                            <span class="me-3"><i class="fas fa-envelope"></i><?= htmlspecialchars($userEmail) ?></span>
                            <span><i class="fas fa-microchip"></i><?= $totalDevices ?> Perangkat</span>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-custom alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle') ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Kolom Kiri: Informasi Profil -->
                    <div class="col-lg-6 mb-4">
                        <div class="card-profile">
                            <div class="card-header">
                                <i class="fas fa-user-edit"></i> Informasi Profil
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" class="form-profile">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Nama Lengkap</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($userName) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Alamat Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" required>
                                        </div>
                                    </div>
                                    <hr class="divider-custom">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save"></i> Simpan Perubahan Profil
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Ubah Kata Sandi -->
                    <div class="col-lg-6 mb-4">
                        <div class="card-profile">
                            <div class="card-header">
                                <i class="fas fa-lock"></i> Keamanan Akun
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" class="form-profile">
                                    <!-- Hidden fields agar data profil tidak hilang -->
                                    <input type="hidden" name="full_name" value="<?= htmlspecialchars($userName) ?>">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($userEmail) ?>">

                                    <div class="password-section">
                                        <div class="form-section-title">
                                            <i class="fas fa-key"></i> Ubah Kata Sandi
                                        </div>
                                        <p class="text-muted small mb-3">Kosongkan jika tidak ingin mengubah kata sandi.</p>

                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Kata Sandi Saat Ini</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Masukkan kata sandi saat ini">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Kata Sandi Baru</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Minimal 6 karakter">
                                            </div>
                                            <div class="form-text">Minimal 6 karakter. Gunakan kombinasi huruf dan angka untuk keamanan lebih.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_new_password" class="form-label">Konfirmasi Kata Sandi Baru</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                                <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" placeholder="Ulangi kata sandi baru">
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="divider-custom">

                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save"></i> Perbarui Kata Sandi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Akun -->
                <div class="card-profile">
                    <div class="card-header">
                        <i class="fas fa-chart-simple"></i> Ringkasan Akun
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center" style="border: none; border-radius: 12px; padding: 16px; background: #f8f9fc; transition: all 0.2s ease; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div style="font-size: 1.5rem; color: #667eea; margin-bottom: 8px;"><i class="fas fa-microchip"></i></div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #2d3748;"><?= $totalDevices ?></div>
                                    <div style="font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Total Perangkat</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center" style="border: none; border-radius: 12px; padding: 16px; background: #f8f9fc; transition: all 0.2s ease; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div style="font-size: 1.5rem; color: #38a169; margin-bottom: 8px;"><i class="fas fa-wifi"></i></div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #2d3748;"><?= $onlineDevices ?></div>
                                    <div style="font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Perangkat Online</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center" style="border: none; border-radius: 12px; padding: 16px; background: #f8f9fc; transition: all 0.2s ease; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div style="font-size: 1.5rem; color: #e53e3e; margin-bottom: 8px;"><i class="fas fa-times-circle"></i></div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #2d3748;"><?= $totalDevices - $onlineDevices ?></div>
                                    <div style="font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Perangkat Offline</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center" style="border: none; border-radius: 12px; padding: 16px; background: #f8f9fc; transition: all 0.2s ease; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div style="font-size: 1.5rem; color: #d69e2e; margin-bottom: 8px;"><i class="fas fa-calendar-check"></i></div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #2d3748;">Aktif</div>
                                    <div style="font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Status Akun</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });

        // --- Navbar Alert Loading Functions ---
        const USER_ID = <?= json_encode($userId) ?>;
        const API_BASE_URL = '/lantaburateknologi/api/'; // Sesuaikan jika path API Anda berbeda

        function fetchNavbarAlerts() {
            if (!USER_ID) {
                console.error("USER_ID not defined for fetching navbar alerts.");
                return;
            }

            fetch(`${API_BASE_URL}get_unread_alerts.php?user_id=${USER_ID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('unread-alerts-count').innerText = data.unread_count;
                        const alertsList = document.getElementById('recent-alerts-list');
                        alertsList.innerHTML = '';

                        if (data.recent_alerts.length > 0) {
                            data.recent_alerts.forEach(alert => {
                                const alertItem = document.createElement('a');
                                alertItem.className = 'dropdown-item d-flex align-items-center';
                                alertItem.href = `notification.php?alert_id=${alert.id}`;

                                let iconClass = 'fas fa-exclamation-triangle text-warning';
                                if (alert.severity === 'error') {
                                    iconClass = 'fas fa-exclamation-circle text-danger';
                                } else if (alert.severity === 'info') {
                                    iconClass = 'fas fa-info-circle text-primary';
                                } else if (alert.severity === 'success') {
                                    iconClass = 'fas fa-check-circle text-success';
                                }

                                alertItem.innerHTML = `
                                    <div class="me-3">
                                        <div class="icon-circle bg-primary" style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="${iconClass} text-white" style="font-size: 0.8rem;"></i>
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

        document.addEventListener('DOMContentLoaded', function() {
            fetchNavbarAlerts();
            setInterval(fetchNavbarAlerts, 30000);
        });
    </script>
</body>
</html>
