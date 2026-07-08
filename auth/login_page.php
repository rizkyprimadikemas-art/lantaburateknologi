<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lantabur Teknologi</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --bg-main: #0f172a;
            --bg-glass: rgba(255, 255, 255, 0.05);
            --border-glass: rgba(255, 255, 255, 0.1);

            --primary: #3b82f6; /* Blue */
            --secondary: #8b5cf6; /* Purple */
            --accent: #22d3ee; /* Cyan */

            --text-main: #f1f5f9;
            --text-secondary: #94a3b8;
        }

        * {
            box-sizing: border-box; /* Penting untuk layout yang konsisten */
            transition: all 0.2s ease-in-out;
        }

        body {
            background: radial-gradient(circle at top, #1e293b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .login-container {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .brand-section {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            padding: 40px;
            text-align: center;
            display: flex; /* Menggunakan flexbox untuk konten vertikal */
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100%; /* Memastikan mengambil tinggi penuh */
        }

        .brand-logo {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--accent);
            text-shadow: 0 0 20px var(--accent);
        }

        .brand-title {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .brand-subtitle {
            color: var(--text-secondary);
        }

        .login-form-section {
            padding: 40px;
            display: flex; /* Menggunakan flexbox untuk konten vertikal */
            flex-direction: column;
            justify-content: center;
            min-height: 100%; /* Memastikan mengambil tinggi penuh */
        }

        .login-form-section form {
            max-width: 400px; /* Batasi lebar form agar lebih minimalis */
            margin: 0 auto; /* Pusatkan form */
            width: 100%; /* Pastikan mengambil lebar penuh dari max-width */
        }

        .form-title {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .form-control {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 12px;
            color: var(--text-main); /* Pastikan teks input terlihat */
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(59,130,246,0.3); /* Efek shadow lebih lembut */
            background: rgba(255,255,255,0.08);
            color: var(--text-main);
        }

        .input-group-text {
            background: rgba(255,255,255,0.05); /* Background konsisten dengan input */
            border: 1px solid var(--border-glass);
            color: var(--text-secondary);
            border-radius: 10px; /* Radius konsisten */
        }

        /* Penyesuaian border-radius untuk input group agar menyatu */
        .input-group .form-control:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group .input-group-text:not(:first-child):not(:last-child) {
            border-radius: 0;
        }
        .input-group .input-group-text:first-child:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group .input-group-text:last-child:not(:first-child) {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .input-group:hover .input-group-text i {
            color: var(--primary);
        }

        .password-toggle {
            cursor: pointer;
            user-select: none;
        }
        .password-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--primary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease; /* Transisi lebih halus */
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(59,130,246,0.5);
            opacity: 0.9;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin-top: 30px;
            text-align: left; /* Rata kiri untuk daftar fitur */
            max-width: 300px; /* Batasi lebar daftar fitur */
            margin-left: auto;
            margin-right: auto;
        }

        .features-list li {
            padding: 8px 0;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }

        .features-list li i {
            color: var(--accent);
            margin-right: 10px;
            min-width: 20px; /* Pastikan ikon tidak bergeser */
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            font-weight: 500;
            text-align: center;
            padding: 12px 20px;
            margin-top: 20px;
            /* Gaya alert agar cocok dengan tema gelap */
            background-color: rgba(var(--bs-success-rgb), 0.2); /* Contoh: Green dengan transparansi */
            color: var(--bs-success); /* Warna teks hijau */
            border-color: rgba(var(--bs-success-rgb), 0.3);
        }
        .alert-danger {
            background-color: rgba(var(--bs-danger-rgb), 0.2); /* Contoh: Red dengan transparansi */
            color: var(--bs-danger); /* Warna teks merah */
            border-color: rgba(var(--bs-danger-rgb), 0.3);
        }

        @media (max-width: 768px) {
            .brand-section {
                display: none;
            }
            .login-form-section {
                padding: 30px; /* Kurangi padding di mobile */
            }
            .login-form-section form {
                max-width: 100%; /* Gunakan lebar penuh di mobile */
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="login-container">
                <div class="row g-0">

                    <!-- LEFT - Brand Section -->
                    <div class="col-lg-6 d-none d-lg-flex"> <!-- Menggunakan d-lg-flex untuk flexbox di desktop -->
                        <div class="brand-section">
                            <div class="brand-logo">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <h1 class="brand-title">Lantabur Teknologi</h1>
                            <p class="brand-subtitle">Energy Monitoring System</p>

                            <ul class="features-list">
                                <li><i class="fas fa-check-circle"></i> Monitoring listrik real-time</li>
                                <li><i class="fas fa-check-circle"></i> Analisis efisiensi</li>
                                <li><i class="fas fa-check-circle"></i> Estimasi biaya</li>
                                <li><i class="fas fa-check-circle"></i> Notifikasi perangkat</li>
                                <li><i class="fas fa-check-circle"></i> Multi perangkat</li>
                                <li><i class="fas fa-check-circle"></i> Dashboard interaktif</li>
                            </ul>
                        </div>
                    </div>

                    <!-- RIGHT - Login Form Section -->
                    <div class="col-lg-6">
                        <div class="login-form-section">

                            <h2 class="form-title">Masuk ke Akun</h2>

                            <form id="loginForm">

                                <div class="mb-4">
                                    <label class="form-label text-white">Username / Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-white">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <span class="input-group-text password-toggle" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-login w-100 mb-3">
                                    Login
                                </button>

                                <div class="register-link">
                                    Belum punya akun? <a href="register_page.php">Daftar</a>
                                </div>

                            </form>

                            <div id="message" class="mt-3"></div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('togglePassword').onclick = function () {
    let input = document.getElementById('password');
    let icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
};

document.getElementById('loginForm').addEventListener('submit', async function(e){
    e.preventDefault();

    let formData = new FormData(this);
    let messageDiv = document.getElementById('message');

    messageDiv.innerHTML = `<div class="alert alert-info">Memproses...</div>`; // Pesan loading

    try {
        let res = await fetch('login.php', {
            method: 'POST',
            body: formData
        });

        let result = await res.json();

        if(result.status === 'success'){
            messageDiv.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
            setTimeout(()=> location.href='../dashboard/',1000);
        } else {
            messageDiv.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
        }

    } catch(err){
        console.error('Fetch error:', err);
        messageDiv.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan server atau jaringan.</div>`;
    }
});
</script>

</body>
</html>
