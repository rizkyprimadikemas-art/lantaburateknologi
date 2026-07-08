<?php
// File: dashboard/devices/list.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php'; // Pastikan ini ada untuk getPDOConnection()

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$userName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Pengguna';
$userEmail = $currentUser['email'] ?? 'email@example.com';

$devices = [];

// Definisikan ambang batas untuk menganggap perangkat online (konsisten dengan dashboard)
// Perangkat dianggap online jika last_seen-nya kurang dari atau sama dengan 5 menit yang lalu
$STALE_DATA_THRESHOLD_SECONDS = 300; // 5 menit

try {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("
        SELECT
            id,
            device_name,
            device_id,
            device_type,
            location,
            is_online, -- Ini adalah status yang dilaporkan perangkat, mungkin tidak real-time
            last_seen,
            (CASE WHEN last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= ? THEN 1 ELSE 0 END) AS is_truly_online
        FROM devices
        WHERE user_id = ? AND is_active = 1 -- Hanya tampilkan perangkat yang aktif
        ORDER BY created_at DESC
    ");
    $stmt->execute([$STALE_DATA_THRESHOLD_SECONDS, $userId]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching devices: " . $e->getMessage());
    // Anda bisa menampilkan pesan error yang lebih ramah pengguna di sini
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Perangkat Saya - Lantabur Teknologi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* Gaya yang sudah ada */
        .device-status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .online { background-color: #28a745; } /* Green */
        .offline { background-color: #dc3545; } /* Red */

        /* Gaya baru untuk kartu perangkat */
        .device-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border-radius: 12px; /* Sudut lebih membulat */
            overflow: hidden; /* Pastikan konten tidak meluber */
        }
        .device-card:hover {
            transform: translateY(-5px); /* Efek naik sedikit saat hover */
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important; /* Bayangan lebih kuat saat hover */
        }
        .device-card .card-title a {
            color: inherit; /* Pastikan warna teks link sesuai dengan teks kartu */
            text-decoration: none; /* Hilangkan garis bawah link */
        }
        .device-card .card-subtitle {
            font-size: 0.85rem;
        }
        .device-card .card-body {
            padding-bottom: 0.5rem; /* Kurangi padding bawah body */
        }
        .device-card .card-footer {
            background-color: transparent; /* Pastikan latar belakang footer transparan */
            border-top: none; /* Hilangkan border atas footer */
            padding-top: 0.5rem; /* Kurangi padding atas footer */
            padding-bottom: 1rem; /* Tambah padding bawah footer */
        }
        .device-card .badge {
            font-size: 0.75rem;
            padding: 0.4em 0.6em;
        }
        .device-card .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
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
                            <a class="nav-link" href="#" id="sync-button"><i class="fas fa-sync-alt"></i> Sync</a>
                        </li>
                        <!-- START: Navbar Alerts Dropdown (BARU/Diperbaiki) -->
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mt-4 mb-0 fw-bold">Perangkat Saya</h1>
                    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Tambah Perangkat</a>
                </div>
                <p class="lead text-muted mb-4">Kelola semua perangkat monitoring energi Anda di sini.</p>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($devices)): ?>
                    <div class="alert alert-info text-center py-4" role="alert">
                        <h4 class="alert-heading"><i class="fas fa-box-open me-2"></i> Belum Ada Perangkat</h4>
                        <p>Anda belum memiliki perangkat monitoring energi yang terdaftar. Mulai tambahkan perangkat baru untuk melacak konsumsi energi Anda!</p>
                        <hr>
                        <a href="add.php" class="btn btn-info"><i class="fas fa-plus me-1"></i> Tambah Perangkat Pertama</a>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"> <!-- Grid untuk kartu perangkat -->
                        <?php foreach ($devices as $device): ?>
                            <div class="col" id="device-card-<?= $device['id'] ?>"> <!-- Tambahkan ID untuk JS delete -->
                                <div class="card h-100 shadow-sm border-0 device-card">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title mb-2">
                                            <a href="detail.php?id=<?= $device['id'] ?>" class="text-decoration-none fw-bold">
                                                <?= htmlspecialchars($device['device_name']) ?>
                                            </a>
                                        </h5>
                                        <p class="card-subtitle mb-2 text-muted">
                                            <i class="fas fa-fingerprint me-1"></i> ID: <span class="fw-light"><?= htmlspecialchars($device['device_id']) ?></span>
                                        </p>
                                        <div class="mb-3">
                                            <span class="badge bg-secondary me-2"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars($device['device_type']) ?></span>
                                            <?php if ($device['location']): ?>
                                                <span class="badge bg-info text-dark"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($device['location']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mt-auto pt-2 border-top">
                                            <?php
                                            // Tentukan status berdasarkan 'is_truly_online' yang dihitung di query
                                            $isTrulyOnline = $device['is_truly_online'];
                                            $statusClass = $isTrulyOnline ? 'online' : 'offline';
                                            $statusText = $isTrulyOnline ? 'Online' : 'Offline';
                                            ?>
                                            <span class="device-status-indicator <?= $statusClass ?>"></span>
                                            <span class="fw-semibold me-2 text-nowrap"><?= $statusText ?></span>
                                            <?php if ($device['last_seen']): ?>
                                                <span class="text-muted small ms-auto text-end">Terakhir: <?= htmlspecialchars($device['last_seen']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0 d-flex justify-content-end gap-2">
                                        <a href="detail.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="fas fa-eye"></i> Detail</a>
                                        <a href="edit.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button class="btn btn-sm btn-outline-danger delete-device-btn" data-id="<?= $device['id'] ?>" title="Hapus"><i class="fas fa-trash"></i> Hapus</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const USER_ID = <?= json_encode($userId) ?>;
        // Path API relatif dari devices/list.php adalah '../../api/'
        const API_BASE_URL = '../../api/'; 

        // Fungsi untuk Notifikasi Dropdown di Navbar (diulang untuk setiap halaman yang memiliki navbar)
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
                                // Link ke notification.php, relatif dari devices/list.php
                                alertItem.href = `../settings/notification.php?alert_id=${alert.id}`; 

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


        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.getElementById('menu-toggle').addEventListener('click', function() {
                document.getElementById('wrapper').classList.toggle('toggled');
            });

            // Panggil fetchNavbarAlerts saat DOMContentLoaded dan atur interval refresh
            fetchNavbarAlerts();
            setInterval(fetchNavbarAlerts, 30000); // Refresh setiap 30 detik

            // Logika tombol Sync untuk halaman ini
            document.getElementById('sync-button').addEventListener('click', function(event) {
                event.preventDefault(); // Mencegah perilaku default href="#"

                const syncIcon = this.querySelector('i');
                syncIcon.classList.add('fa-spin'); // Tambahkan animasi berputar
                this.classList.add('disabled'); // Nonaktifkan tombol sementara

                // Untuk halaman daftar perangkat, "Sync" berarti memuat ulang seluruh halaman
                // agar data perangkat terbaru (termasuk status online) diambil dari server.
                window.location.reload(); 
                // Animasi spinner akan berhenti secara otomatis saat halaman dimuat ulang.
            });

            // Delete functionality
            document.querySelectorAll('.delete-device-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const deviceId = this.dataset.id;
                    if (confirm('Apakah Anda yakin ingin menghapus perangkat ini? Semua data terkait juga akan dihapus.')) {
                        fetch('delete_device.php', { // Path relatif ke delete_device.php
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ id: deviceId }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert('Perangkat berhasil dihapus!');
                                // Hapus elemen kartu perangkat dari DOM
                                const deviceCardElement = document.getElementById(`device-card-${deviceId}`);
                                if (deviceCardElement) {
                                    deviceCardElement.remove();
                                }
                                
                                // Periksa apakah tidak ada perangkat lagi, jika ya, refresh halaman untuk menampilkan pesan "belum ada perangkat"
                                const deviceCardsContainer = document.querySelector('.row.row-cols-1');
                                if (deviceCardsContainer && deviceCardsContainer.children.length === 0) {
                                    window.location.reload(); 
                                }
                            } else {
                                alert('Gagal menghapus perangkat: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Terjadi kesalahan saat menghapus perangkat.');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
