<?php
// File: Dashboard/settings/notification.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$userEmail = $currentUser['email'] ?? 'email@example.com';

$notifications = [];
try {
    $pdo = getPDOConnection();
    // Ambil semua notifikasi, urutkan berdasarkan created_at terbaru
    $stmt = $pdo->prepare("SELECT id, message, created_at, is_read, severity FROM alerts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Notification Load Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Terjadi kesalahan database saat memuat notifikasi.";
}

// Jika ada alert_id di URL, tandai sebagai sudah dibaca
if (isset($_GET['alert_id']) && !empty($_GET['alert_id'])) {
    $alertToMarkRead = (int)$_GET['alert_id'];
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
        $stmt->execute([$alertToMarkRead, $userId]);
        header('Location: notification.php');
        exit();
    } catch (PDOException $e) {
        error_log("Error marking alert as read from URL: " . $e->getMessage());
        $_SESSION['error_message'] = "Gagal menandai notifikasi sebagai sudah dibaca.";
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Lantabura Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .notification-item {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: flex-start; /* Align items to the top */
        }
        .notification-item.unread {
            background-color: #e8f0fe; /* Warna latar belakang untuk notifikasi belum dibaca */
            border-left: 5px solid #007bff;
        }
        .notification-item .icon-wrapper {
            margin-right: 15px;
            font-size: 1.5rem;
            line-height: 1;
        }
        .notification-item .message-content {
            flex-grow: 1;
        }
        .notification-item .message-text {
            margin-bottom: 5px;
        }
        .notification-item .timestamp {
            font-size: 0.8em;
            color: #6c757d;
        }
        .notification-item .actions {
            margin-left: 15px;
            flex-shrink: 0; /* Prevent actions from shrinking */
        }
        .severity-info { color: #007bff; }
        .severity-warning { color: #ffc107; }
        .severity-error { color: #dc3545; }
        .severity-success { color: #28a745; }
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
                <a href="notification.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-bell"></i> Notifikasi
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action">
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
                        
                        <!-- NOTIFIKASI DROPDOWN (Sama seperti di dashboard) -->
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
                                    <!-- Alerts will be loaded here by JavaScript -->
                                    <a class="dropdown-item text-center small text-gray-500 py-2" href="#">Memuat peringatan...</a>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500 py-2" href="../settings/notification.php">Lihat Semua Peringatan</a>
                            </div>
                        </li>
                        <!-- AKHIR NOTIFIKASI DROPDOWN -->

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
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h3 class="mb-0">Semua Notifikasi Anda</h3>
                    <button class="btn btn-sm btn-outline-primary" id="mark-all-as-read-btn">
                        <i class="fas fa-check-double"></i> Tandai Semua Sudah Dibaca
                    </button>
                </div>

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

                <div class="notification-list mt-4">
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info" role="alert">
                            Tidak ada notifikasi.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                                $isReadClass = $notification['is_read'] ? '' : 'unread';
                                $iconClass = 'fas fa-info-circle severity-info'; // Default
                                if ($notification['severity'] === 'error') {
                                    $iconClass = 'fas fa-exclamation-circle severity-error';
                                } elseif ($notification['severity'] === 'warning') {
                                    $iconClass = 'fas fa-exclamation-triangle severity-warning';
                                } elseif ($notification['severity'] === 'success') {
                                    $iconClass = 'fas fa-check-circle severity-success';
                                }
                            ?>
                            <div class="notification-item <?= $isReadClass ?>" id="notification-<?= htmlspecialchars($notification['id']) ?>">
                                <div class="icon-wrapper">
                                    <i class="<?= $iconClass ?>"></i>
                                </div>
                                <div class="message-content">
                                    <div class="message-text">
                                        <?= htmlspecialchars($notification['message']) ?>
                                    </div>
                                    <div class="timestamp">
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))) ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="<?= htmlspecialchars($notification['id']) ?>">
                                            <i class="fas fa-check"></i> Tandai Sudah Dibaca
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?= htmlspecialchars($notification['id']) ?>">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>    <script>
        const USER_ID = <?= json_encode($userId) ?>;
        const API_BASE_URL = '../../api/'; // Sesuaikan path API dari notification.php

        // Toggle sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });

        // --- Fungsi untuk Notifikasi Dropdown di Navbar (Duplikasi yang benar) ---
        // Fungsi ini diperlukan di setiap halaman yang menggunakan navbar yang sama
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
                        alertsList.innerHTML = ''; // Bersihkan daftar sebelumnya

                        if (data.recent_alerts.length > 0) {
                            data.recent_alerts.forEach(alert => {
                                const alertItem = document.createElement('a');
                                alertItem.className = 'dropdown-item d-flex align-items-center';
                                alertItem.href = `notification.php?alert_id=${alert.id}`; // Link ke halaman ini
                                // Tidak perlu onclick untuk mark as read di sini, karena PHP di halaman ini akan menanganinya
                                // saat navigasi dan reload halaman.

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
        // --- Akhir Fungsi Notifikasi Dropdown ---


        // Fungsi untuk menandai notifikasi sebagai sudah dibaca atau menghapus
        function handleAlertAction(alertId, actionType) { // actionType: 'read' atau 'delete'
            const url = (actionType === 'read') ? `${API_BASE_URL}mark_alert_as_read.php` : `${API_BASE_URL}delete_notification.php`;
            const body = (actionType === 'read') ? { alert_id: alertId, user_id: USER_ID } : { notification_id: alertId, user_id: USER_ID };
            const confirmMsg = (actionType === 'read') ? "Apakah Anda yakin ingin menandai notifikasi ini sudah dibaca?" : "Apakah Anda yakin ingin menghapus notifikasi ini?";

            if (!confirm(confirmMsg)) {
                return;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload(); // Reload halaman untuk memperbarui status is_read dan daftar
                } else {
                    alert('Gagal ' + (actionType === 'read' ? 'menandai sudah dibaca' : 'menghapus') + ' notifikasi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat melakukan tindakan.');
            });
        }

        // Event listener untuk tombol "Tandai Sudah Dibaca"
        document.querySelectorAll('.mark-read-btn').forEach(button => {
            button.addEventListener('click', function() {
                const alertId = this.dataset.id;
                handleAlertAction(alertId, 'read');
            });
        });

        // Event listener untuk tombol "Hapus"
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const alertId = this.dataset.id;
                handleAlertAction(alertId, 'delete');
            });
        });

        // Event listener untuk tombol "Tandai Semua Sudah Dibaca"
        document.getElementById('mark-all-as-read-btn').addEventListener('click', function() {
            if (!confirm("Apakah Anda yakin ingin menandai SEMUA notifikasi sebagai sudah dibaca?")) {
                return;
            }

            fetch(`${API_BASE_URL}mark_alert_as_read.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: USER_ID,
                    mark_all: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload(); // Reload halaman setelah semua ditandai
                } else {
                    alert('Gagal menandai semua notifikasi sebagai sudah dibaca: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menandai semua notifikasi.');
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            fetchNavbarAlerts(); // Panggil saat DOMContentLoaded untuk navbar
            setInterval(fetchNavbarAlerts, 30000); // Refresh badge notifikasi setiap 30 detik
        });
    </script>
</body>
</html>
