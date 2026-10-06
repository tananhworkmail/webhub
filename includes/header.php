<?php
if (!isset($pageTitle, $activePage)) exit;
require_once __DIR__ . '/../config.php';
ensureStorage();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('webhub-theme')||'light'}catch(e){}</script>
    <script src="assets/js/app.js?v=5" defer></script>
</head>
<body data-page="<?= htmlspecialchars($activePage, ENT_QUOTES, 'UTF-8') ?>">
<header class="app-header">
    <a class="brand" href="index.php"><span class="brand-mark">W</span><strong>WEB HUB</strong></a>
    <nav class="top-nav" aria-label="Điều hướng chính">
        <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><span>◫</span>Dashboard</a>
        <a class="<?= $activePage === 'all' ? 'active' : '' ?>" href="index.php"><span>▦</span>Tất cả web</a>
        <a class="<?= $activePage === 'manager' ? 'active' : '' ?>" href="manager.php"><span>＋</span>Quản lý web</a>
    </nav>
    <div class="header-actions">
        <button class="icon-btn" id="themeBtn" type="button" aria-label="Đổi giao diện sáng tối">◐</button>
    </div>
</header>
<main class="page-content">
