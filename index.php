<?php
$pageTitle = 'Tất cả web';
$activePage = 'all';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div><h1>Tất cả website</h1><p>Chọn một website đang online để mở.</p></div>
    <button class="btn btn-primary" id="refreshAllBtn" type="button">↻ Kiểm tra lại</button>
</section>

<section class="toolbar panel">
    <label class="search-box"><span>⌕</span><input id="allSearch" type="search" placeholder="Tìm tên website hoặc URL..." autocomplete="off"></label>
    <select id="allGroupFilter" aria-label="Lọc theo nhóm"><option value="all">Tất cả nhóm</option></select>
    <span class="check-time" id="allCheckedAt">Chưa kiểm tra</span>
</section>

<section id="publicSiteGrid" class="public-site-grid" aria-live="polite"></section>
<div id="publicSiteEmpty" class="empty-state panel" hidden><span>◎</span><h2>Chưa có website</h2><p>Vào trang Quản lý web để thêm website đầu tiên.</p><a class="btn btn-primary" href="manager.php">Quản lý web</a></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
