<?php
require __DIR__ . '/config.php';
ensureStorage();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <meta name="description" content="Không gian tổng hợp các website và ứng dụng cá nhân.">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> — Không gian ứng dụng của tôi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('webhub-theme') || 'light'; } catch (_) {}</script>
    <script src="assets/js/app.js?v=2" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="./" aria-label="Trang chủ Web Hub">
            <span class="brand-symbol"><span></span><span></span><span></span><span></span></span>
            <span class="brand-copy"><strong>Web Hub<span class="brand-dot">.</span></strong><small>Không gian của tôi</small></span>
        </a>

        <div class="sidebar-group-label">KHÁM PHÁ</div>
        <nav class="side-nav" id="categoryNav" aria-label="Danh mục website">
            <button class="nav-item active" type="button" data-category="all"><span class="nav-icon">▦</span><span>Tất cả website</span><span class="nav-count" id="allCount">0</span></button>
            <button class="nav-item" type="button" data-category="pinned"><span class="nav-icon">✦</span><span>Đã ghim</span><span class="nav-count" id="pinnedCount">0</span></button>
            <div class="nav-divider"></div>
            <div id="categoryLinks"></div>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-note"><span class="note-sparkle">✦</span><strong>Mọi app, một chỗ.</strong><p>Lưu lại các công cụ bạn đã tạo để mở nhanh bất cứ lúc nào.</p></div>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu icon-button" id="menuBtn" type="button" aria-label="Mở danh mục">☰</button>
                <div class="breadcrumb"><span>Không gian</span><span class="breadcrumb-slash">/</span><strong id="breadcrumbCurrent">Tất cả website</strong></div>
            </div>
            <div class="topbar-actions">
                <span class="today-label" id="todayLabel"></span>
                <button class="icon-button theme-button" id="themeBtn" type="button" aria-label="Đổi giao diện sáng tối">◐</button>
            </div>
        </header>

        <main class="content">
            <section class="welcome">
                <div class="welcome-copy">
                    <div class="eyebrow"><span class="eyebrow-dot"></span> WEB HUB · ỨNG DỤNG CÁ NHÂN</div>
                    <h1>Một nơi cho mọi<br><em>ý tưởng của bạn.</em></h1>
                    <p>Khám phá và mở nhanh những website, ứng dụng hữu ích được lưu ở đây. Mỗi dự án đều cách bạn một cú nhấp.</p>
                    <div class="welcome-actions">
                        <a href="#collection" class="primary-link">Khám phá ứng dụng <span>↗</span></a>
                        <button class="text-link" id="heroAddBtn" type="button">+ Thêm website mới</button>
                    </div>
                </div>
                <div class="welcome-visual" aria-hidden="true">
                    <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
                    <div class="visual-card visual-card-one"><span>✦</span><i></i><i></i></div>
                    <div class="visual-card visual-card-two"><span>▥</span><i></i><i></i></div>
                    <div class="visual-card visual-card-three"><span>⌁</span><i></i><i></i></div>
                    <div class="visual-center"><span>W</span></div>
                </div>
            </section>

            <section class="summary-row" aria-label="Tổng quan">
                <div class="summary-item"><span class="summary-icon indigo">▦</span><div><strong id="totalCount">0</strong><small>Website</small></div></div>
                <div class="summary-item"><span class="summary-icon pink">◫</span><div><strong id="categoryCount">0</strong><small>Danh mục</small></div></div>
                <div class="summary-item"><span class="summary-icon amber">✦</span><div><strong id="featuredCount">0</strong><small>Đã ghim</small></div></div>
                <div class="summary-caption">Bộ sưu tập ứng dụng<br>được cập nhật liên tục <span>↗</span></div>
            </section>

            <section class="collection" id="collection">
                <div class="section-heading">
                    <div><span class="section-kicker">THƯ VIỆN ỨNG DỤNG</span><h2 id="sectionTitle">Tất cả website</h2><p id="sectionDescription">Các dự án và công cụ trong một không gian chung.</p></div>
                    <button class="add-button" id="addSiteBtn" type="button"><span>＋</span> Thêm website</button>
                </div>
                <div class="toolbar">
                    <label class="search-field"><span class="search-icon">⌕</span><input id="searchInput" type="search" placeholder="Tìm tên, mô tả, đường dẫn..." autocomplete="off"><kbd>/</kbd></label>
                    <select id="sortSelect" aria-label="Sắp xếp website"><option value="recent">Mới cập nhật</option><option value="name">Tên A–Z</option></select>
                    <span class="result-count" id="resultCount">0 kết quả</span>
                </div>
                <div class="site-grid" id="siteGrid"></div>
                <div class="empty-state" id="emptyState" hidden>
                    <div class="empty-symbol">✳</div>
                    <h3 id="emptyTitle">Chưa có website nào</h3>
                    <p id="emptyText">Đăng nhập quản trị để thêm website đầu tiên vào bộ sưu tập.</p>
                    <button class="empty-action" id="emptyAddBtn" type="button">＋ Thêm website</button>
                </div>
            </section>
            <footer><span>© <?= date('Y') ?> Web Hub</span><span>Được tạo để kết nối những ý tưởng nhỏ.</span></footer>
        </main>
    </div>
</div>
<div class="sidebar-shade" id="sidebarShade" hidden></div>

<div class="modal-backdrop" id="siteModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header"><div><span class="section-kicker">QUẢN LÝ WEBSITE</span><h2 id="modalTitle">Thêm website</h2></div><button class="modal-close" id="closeModalBtn" type="button" aria-label="Đóng">×</button></div>
        <form id="siteForm">
            <input type="hidden" id="siteId"><input type="hidden" id="siteImage">
            <div class="form-row">
                <label class="form-field"><span>Tên website <b>*</b></span><input id="siteName" maxlength="100" required placeholder="Ví dụ: Quản lý chi tiêu"></label>
                <label class="form-field"><span>Danh mục</span><input id="siteCategory" maxlength="60" list="categorySuggestions" placeholder="Tiện ích"><datalist id="categorySuggestions"><option value="Tiện ích"><option value="Đời sống"><option value="Công việc"><option value="Tài chính"><option value="Giải trí"><option value="Học tập"><option value="Khác"></datalist></label>
            </div>
            <label class="form-field"><span>Đường dẫn website <b>*</b></span><input id="siteUrl" type="text" inputmode="url" required placeholder="https://ten-web.infinityfreeapp.com"></label>
            <label class="form-field"><span>Mô tả ngắn</span><textarea id="siteDescription" maxlength="500" rows="3" placeholder="Website này giúp bạn làm gì?"></textarea></label>
            <div class="form-field"><span>Ảnh đại diện</span><div class="image-picker"><div id="imagePreview" class="image-preview"><span>Thumbnail tự động từ URL</span></div><div class="image-controls"><label class="upload-button">↑ Tải ảnh lên<input id="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label><button id="removeImageBtn" class="remove-image" type="button">Dùng thumbnail URL</button><small>JPG, PNG, WEBP hoặc GIF · tối đa 5 MB.<br>Để trống để tự lấy thumbnail từ URL.</small></div></div></div>
            <label class="pin-checkbox"><input id="sitePinned" type="checkbox"><span>✦</span> Ghim website lên đầu danh sách</label>
            <div class="form-actions"><button class="cancel-button" id="cancelBtn" type="button">Hủy</button><button class="save-button" id="saveBtn" type="submit">Lưu website <span>↗</span></button></div>
        </form>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
