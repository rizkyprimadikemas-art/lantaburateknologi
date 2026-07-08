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
<title>Register - Lantabur Teknologi</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- Icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
:root {
    --bg-main: #0f172a;
    --bg-glass: rgba(255, 255, 255, 0.05);
    --border-glass: rgba(255, 255, 255, 0.1);

    --primary: #3b82f6;
    --secondary: #8b5cf6;
    --accent: #22d3ee;

    --text-main: #f1f5f9;
    --text-secondary: #94a3b8;
}

* {
    transition: all 0.2s ease-in-out;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    background: radial-gradient(circle at top, #1e293b, #0f172a);
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
}

/* CENTER FIX */
.container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* CARD */
.register-container {
    position: relative;
    width: 100%;
    max-width: 1000px;
    background: var(--bg-glass);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-glass);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 
        0 20px 60px rgba(0,0,0,0.6),
        0 0 40px rgba(59,130,246,0.1);
    animation: fadeIn 0.6s ease;
}

/* glow bawah */
.register-container::before {
    content: "";
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 70%;
    height: 50px;
    background: radial-gradient(circle, rgba(59,130,246,0.4), transparent);
    filter: blur(25px);
    z-index: -1;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(20px);}
    to {opacity: 1; transform: translateY(0);}
}

/* ROW FIX */
.register-container .row {
    min-height: 500px;
}

/* LEFT */
.brand-section {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    padding: 40px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
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

.features-list {
    list-style: none;
    padding: 0;
    margin-top: 30px;
}

.features-list li {
    padding: 8px 0;
    color: var(--text-secondary);
}

.features-list li i {
    color: var(--accent);
    margin-right: 10px;
}

/* RIGHT */
.register-form-section {
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.form-title {
    text-align: center;
    margin-bottom: 30px;
    font-weight: 600;
}

/* INPUT */
.form-control {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-glass);
    border-radius: 10px;
    padding: 12px;
    color: white;
}

.form-control::placeholder {
    color: #94a3b8;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 10px var(--primary);
    background: rgba(255,255,255,0.08);
}

/* BUTTON */
.btn-register {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    border-radius: 12px;
    padding: 12px;
    font-weight: 600;
    color: white;
}

.btn-register:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(59,130,246,0.5);
}

/* LINK */
.login-link {
    text-align: center;
    margin-top: 20px;
    color: var(--text-secondary);
}

.login-link a {
    color: var(--primary);
    text-decoration: none;
}

/* PASSWORD BAR */
.password-strength {
    height: 5px;
    border-radius: 5px;
    margin-top: 5px;
}

.strength-weak { background: red; width: 25%; }
.strength-fair { background: orange; width: 50%; }
.strength-good { background: limegreen; width: 75%; }
.strength-strong { background: cyan; width: 100%; }

/* MOBILE */
@media (max-width: 768px) {
    .brand-section {
        display: none;
    }
}
</style>
</head>

<body>

<div class="container">
<div class="register-container">
<div class="row g-0">

<!-- LEFT -->
<div class="col-lg-6 d-none d-lg-block">
<div class="brand-section">
<div class="brand-logo"><i class="fas fa-bolt"></i></div>
<h1 class="brand-title">Lantabur Teknologi</h1>
<p class="brand-subtitle">Energy Monitoring System</p>

<ul class="features-list">
<li><i class="fas fa-check-circle"></i> Monitoring listrik real-time</li>
<li><i class="fas fa-check-circle"></i> Analisis efisiensi</li>
<li><i class="fas fa-check-circle"></i> Estimasi biaya</li>
<li><i class="fas fa-check-circle"></i> Notifikasi device</li>
<li><i class="fas fa-check-circle"></i> Multi device</li>
<li><i class="fas fa-check-circle"></i> Dashboard interaktif</li>
</ul>
</div>
</div>

<!-- RIGHT -->
<div class="col-lg-6">
<div class="register-form-section">

<h2 class="form-title">Buat Akun</h2>

<form id="registerForm">

<div class="mb-3">
<input type="text" class="form-control" name="full_name" placeholder="Nama Lengkap" required>
</div>

<div class="mb-3">
<input type="text" class="form-control" name="username" placeholder="Username" required>
</div>

<div class="mb-3">
<input type="email" class="form-control" name="email" placeholder="Email" required>
</div>

<div class="mb-3">
<input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
<div id="strength" class="password-strength"></div>
</div>

<div class="mb-3">
<input type="password" class="form-control" id="confirm" placeholder="Konfirmasi Password" required>
</div>

<button type="submit" class="btn btn-register w-100 mb-3">
Daftar
</button>

<div class="login-link">
Sudah punya akun? <a href="login_page.php">Login</a>
</div>

</form>

<div id="message" class="mt-3"></div>

</div>
</div>

</div>
</div>
</div>

<script>
document.getElementById('password').addEventListener('input', function(){
    let val = this.value;
    let bar = document.getElementById('strength');

    bar.className = 'password-strength';

    if(val.length < 6){
        bar.classList.add('strength-weak');
    } else if(val.length < 8){
        bar.classList.add('strength-fair');
    } else if(/[A-Z]/.test(val) && /[0-9]/.test(val)){
        bar.classList.add('strength-strong');
    } else {
        bar.classList.add('strength-good');
    }
});

document.getElementById('registerForm').addEventListener('submit', async function(e){
    e.preventDefault();

    let message = document.getElementById('message');
    
    // Ambil nilai password dan konfirmasi, lalu trim untuk menghilangkan spasi
    let pass = document.getElementById('password').value.trim();
    let confirm = document.getElementById('confirm').value.trim();
    
    // Debugging: tampilkan panjang karakter dan kode ASCII untuk mendeteksi karakter tersembunyi
    console.log('Password length:', pass.length);
    console.log('Confirm length:', confirm.length);
    console.log('Password chars:', pass.split('').map(c => c.charCodeAt(0)));
    console.log('Confirm chars:', confirm.split('').map(c => c.charCodeAt(0)));

    // Validasi 1: Cek apakah password dan konfirmasi kosong
    if (pass === '' || confirm === '') {
        message.innerHTML = `<div class="alert alert-danger">Password dan konfirmasi password tidak boleh kosong.</div>`;
        return;
    }

    // Validasi 2: Cek panjang password minimal
    if (pass.length < 6) {
        message.innerHTML = `<div class="alert alert-danger">Password minimal 6 karakter.</div>`;
        return;
    }

    // Validasi 3: Bandingkan password dengan konfirmasi menggunakan perbandingan ketat (===)
    if (pass !== confirm) {
        message.innerHTML = `<div class="alert alert-danger">Password dan konfirmasi password tidak sama.</div>`;
        return;
    }

    // Jika lolos semua validasi, kirim data ke server
    let formData = new FormData(this);

    try {
        let res = await fetch('register.php', {
            method: 'POST',
            body: formData
        });

        let result = await res.json();

        if(result.status === 'success'){
            message.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
            setTimeout(()=> location.href='login_page.php', 1500);
        } else {
            message.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
        }
    } catch (error) {
        console.error('Error:', error);
        message.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan koneksi. Silakan coba lagi.</div>`;
    }
});
</script>

</body>
</html>
