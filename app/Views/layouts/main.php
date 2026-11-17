<?php
use App\Helpers\Session;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$pageTitle = $pageTitle ?? 'LaundryKu';
$flashSuccess = Session::flash('success');
$flashError = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <?php require __DIR__ . '/navbar.php'; ?>

    <!-- Flash Notifications -->
    <?php if ($flashSuccess || $flashError): ?>
        <div class="container-fluid px-4 pt-3">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <?php if (isset($showSidebar) && $showSidebar === true): ?>
        <div class="app-wrapper">
            <?php require __DIR__ . '/sidebar.php'; ?>
            <main class="main-content">
                <?= $content ?? '' ?>
            </main>
        </div>
    <?php else: ?>
        <main class="flex-grow-1">
            <?= $content ?? '' ?>
        </main>
    <?php endif; ?>

    <!-- Footer -->
    <?php require __DIR__ . '/footer.php'; ?>

    <!-- AI Chat Assistant Widget -->
    <?php require __DIR__ . '/../partials/ai_chat_widget.php'; ?>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= $baseUrl ?>/js/main.js"></script>
</body>
</html>
