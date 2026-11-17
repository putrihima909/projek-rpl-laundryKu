<?php
$pageTitle = '500 - Kesalahan Server Internal';
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Terjadi Kesalahan Server</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-card { max-width: 540px; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; text-align: center; border: 1px solid #e2e8f0; }
        .error-icon { width: 80px; height: 80px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2 class="fw-bold mb-2">Terjadi Kesalahan Sistem</h2>
        <p class="text-muted mb-4">Maaf, sistem kami mengalami kendala teknis sementara. Tim kami telah mencatat peristiwa ini ke dalam log sistem untuk segera diperbaiki.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= $baseUrl ?>" class="btn btn-primary px-4 rounded-pill">
                <i class="fa-solid fa-house me-2"></i>Kembali ke Beranda
            </a>
            <button onclick="window.location.reload()" class="btn btn-outline-secondary px-4 rounded-pill">
                <i class="fa-solid fa-rotate-right me-2"></i>Muat Ulang
            </button>
        </div>
    </div>
</body>
</html>
