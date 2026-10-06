<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div><h1>Dashboard tổng quan</h1><p>Theo dõi trạng thái, lỗi HTTP, SSL và thời gian phản hồi của các website.</p></div>
    <button class="btn btn-primary" id="refreshDashboardBtn" type="button">↻ Làm mới</button>
</section>

<section class="metric-grid">
    <article class="metric-card"><span>Tổng số web</span><strong id="metricTotal">0</strong></article>
    <article class="metric-card success"><span>Online</span><strong id="metricOnline">0</strong></article>
    <article class="metric-card danger"><span>Offline / lỗi</span><strong id="metricOffline">0</strong></article>
    <article class="metric-card"><span>Phản hồi TB</span><strong><b id="metricAverage">0</b> ms</strong></article>
</section>

<section class="health-grid">
    <article class="panel dashboard-panel">
        <div class="panel-title">◔ Phân bố trạng thái</div>
        <div class="status-bars">
            <div><span>Online</span><i><b class="ok" id="onlineBar"></b></i><strong id="onlineBarCount">0</strong></div>
            <div><span>Offline / lỗi</span><i><b class="bad" id="offlineBar"></b></i><strong id="offlineBarCount">0</strong></div>
        </div>
        <div class="donut-wrap">
            <div class="status-donut" id="statusDonut"><div><strong id="onlinePercent">0%</strong><span>Online</span></div></div>
            <div class="status-legend"><span><i class="dot ok"></i>Online <b id="legendOnline">0</b></span><span><i class="dot bad"></i>Offline / lỗi <b id="legendOffline">0</b></span></div>
        </div>
    </article>

    <article class="panel dashboard-panel">
        <div class="panel-title between"><span>▣ SSL / Domain</span><small id="dashboardCheckedAt">Chưa kiểm tra</small></div>
        <div class="ssl-summary">
            <div><span>DNS lỗi</span><strong id="dnsFailed">0</strong></div>
            <div><span>SSL sắp hết hạn</span><strong id="sslSoon">0</strong></div>
            <div><span>SSL lỗi/hết hạn</span><strong id="sslFailed">0</strong></div>
        </div>
        <div class="domain-list" id="domainList"></div>
        <div class="panel-empty" id="domainEmpty" hidden>Chưa có dữ liệu domain/SSL.</div>
    </article>
</section>

<section class="report-grid">
    <article class="panel dashboard-panel">
        <div class="panel-title">ⓘ Website cần kiểm tra</div>
        <div class="table-scroll">
            <table><thead><tr><th>Website</th><th>HTTP</th><th>Lỗi</th><th>Lần kiểm tra</th></tr></thead><tbody id="problemRows"></tbody></table>
        </div>
        <div class="panel-empty" id="problemEmpty" hidden>Không có website gặp lỗi.</div>
    </article>
    <article class="panel dashboard-panel">
        <div class="panel-title">◴ Phản hồi chậm nhất</div>
        <div class="table-scroll">
            <table><thead><tr><th>Website</th><th>Trạng thái</th><th>Thời gian</th></tr></thead><tbody id="slowRows"></tbody></table>
        </div>
        <div class="panel-empty" id="slowEmpty" hidden>Chưa có dữ liệu phản hồi.</div>
    </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
