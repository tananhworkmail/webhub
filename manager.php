<?php
$pageTitle = 'Quản lý web';
$activePage = 'manager';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div><h1>Quản lý website</h1><p>Thêm, cập nhật và sắp xếp các website.</p></div>
    <div class="manager-tabs" role="tablist">
        <button class="active" type="button" data-manager-tab="sites">Website</button>
        <button type="button" data-manager-tab="groups">Nhóm website</button>
    </div>
</section>

<div id="siteManagerPanel">
    <section class="panel form-card">
        <div class="panel-title between"><span id="siteFormTitle">＋ Thêm website mới</span><button class="btn btn-text" id="resetSiteBtn" type="button">Đặt lại</button></div>
        <form id="siteForm" class="manager-form">
            <input type="hidden" id="siteId"><input type="hidden" id="siteImage">
            <div class="form-main">
                <div>
                    <div class="form-row">
                        <label class="field"><span>Tên website *</span><input id="siteName" maxlength="100" required placeholder="Ví dụ: Quản lý chi tiêu"></label>
                        <label class="field"><span>Nhóm</span><select id="siteGroup"><option value="">Chưa phân nhóm</option></select></label>
                    </div>
                    <label class="field"><span>URL truy cập *</span><input id="siteUrl" type="text" inputmode="url" required placeholder="https://ten-web.infinityfreeapp.com"></label>
                    <label class="field"><span>Mô tả</span><textarea id="siteDescription" maxlength="500" rows="3" placeholder="Mô tả ngắn về website"></textarea></label>
                    <label class="check-field"><input id="sitePinned" type="checkbox"> Ghim website</label>
                </div>
                <div class="form-side">
                    <span class="field-label">Ảnh đại diện</span>
                    <div class="image-preview" id="imagePreview"><span>Thumbnail từ URL</span></div>
                    <label class="btn btn-secondary upload-btn">Tải ảnh lên<input id="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label>
                    <button class="btn btn-text" id="removeImageBtn" type="button">Dùng thumbnail URL</button>
                    <small>JPG, PNG, WEBP hoặc GIF · tối đa 5 MB</small>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" id="saveSiteBtn" type="submit">Lưu website</button></div>
        </form>
    </section>

    <section class="panel table-card">
        <div class="panel-title between">
            <span>Danh sách website <small class="count-tag" id="managerSiteCount">0</small></span>
            <label class="table-search"><span>⌕</span><input id="managerSearch" type="search" placeholder="Tìm website, URL..." autocomplete="off"></label>
        </div>
        <div class="table-scroll">
            <table class="manager-table"><thead><tr><th>Ảnh</th><th>Tên website</th><th>URL</th><th>Nhóm</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody id="managerSiteRows"></tbody></table>
        </div>
        <div class="panel-empty" id="managerSiteEmpty" hidden>Chưa có website nào.</div>
    </section>
</div>

<div id="groupManagerPanel" hidden>
    <section class="panel group-form-card">
        <div class="panel-title"><span id="groupFormTitle">＋ Thêm nhóm website</span></div>
        <form id="groupForm" class="inline-form">
            <input type="hidden" id="groupId">
            <label class="field"><span>Tên nhóm *</span><input id="groupName" maxlength="80" required placeholder="Ví dụ: Công việc"></label>
            <button class="btn btn-primary" id="saveGroupBtn" type="submit">Lưu nhóm</button>
            <button class="btn btn-secondary" id="resetGroupBtn" type="button">Đặt lại</button>
        </form>
    </section>
    <section class="panel table-card">
        <div class="panel-title">Danh sách nhóm <small class="count-tag" id="managerGroupCount">0</small></div>
        <div class="table-scroll">
            <table class="manager-table"><thead><tr><th>Tên nhóm</th><th>Số website</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody id="managerGroupRows"></tbody></table>
        </div>
        <div class="panel-empty" id="managerGroupEmpty" hidden>Chưa có nhóm website.</div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
