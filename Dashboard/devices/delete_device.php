<?php
// File: dashboard/devices/delete_device.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Invalid request.'
];

// Pastikan hanya metode POST yang diizinkan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin(); // Memastikan pengguna sudah login

    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $deviceIdToDelete = $input['id'] ?? null; // Ini adalah ID internal perangkat di DB, BUKAN MAC Address

    if (!$deviceIdToDelete) {
        $response['message'] = 'Device ID is required.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction(); 
        
        // --- MODIFIKASI UTAMA DI SINI ---
        // Ganti DELETE dengan UPDATE untuk melakukan soft delete
        // Kita juga set is_active = 0 dan user_id = NULL agar perangkat tidak lagi mengirim data
        // dan bisa diklaim oleh user lain atau didaftarkan ulang.
        $stmt = $pdo->prepare("UPDATE devices SET is_deleted = 1, is_active = 0, user_id = NULL WHERE id = ? AND user_id = ?");
        $stmt->execute([$deviceIdToDelete, $userId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit(); // Commit transaksi jika berhasil
            $response['status'] = 'success';
            $response['message'] = 'Perangkat berhasil dinonaktifkan dan dihapus dari tampilan Anda. Perangkat dapat didaftarkan ulang.';
        } else {
            $pdo->rollBack(); // Rollback transaksi jika gagal
            $response['message'] = 'Perangkat tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.';
        }

    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback transaksi jika terjadi error
        error_log("Database error soft-deleting device: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan database saat menonaktifkan perangkat.';
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback transaksi jika terjadi error
        error_log("General error soft-deleting device: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan server saat menonaktifkan perangkat.';
    }
}

echo json_encode($response);
exit();
?>
