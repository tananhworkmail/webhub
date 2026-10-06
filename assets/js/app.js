(() => {
    'use strict';
    const api = 'api.php';
    const byId = id => document.getElementById(id);
    const grid = byId('siteGrid');
    const siteModal = byId('siteModal');
    const siteForm = byId('siteForm');
    const searchInput = byId('searchInput');
    const state = { sites: [], category: 'all', csrf: '', previewUrl: '' };
    let toastTimer;
    let previewTimer;

    const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
    const normalizeUrl = value => /^https?:\/\//i.test(value.trim()) ? value.trim() : 'https://' + value.trim();
    const host = value => { try { return new URL(value).hostname.replace(/^www\./, ''); } catch (_) { return value; } };
    const safeUrl = value => { try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : '#'; } catch (_) { return '#'; } };
    const autoThumbnail = value => {
        const url = safeUrl(value);
        return url === '#' ? '' : 'https://image.thum.io/get/width/900/crop/560/noanimate/' + url;
    };
    const coverColor = category => {
        const palette = ['violet', 'blue', 'peach', 'mint', 'rose', 'gold'];
        let hash = 0;
        for (const char of category || '') hash = (hash * 31 + char.charCodeAt(0)) | 0;
        return palette[Math.abs(hash) % palette.length];
    };

    function toast(message, error = false) {
        const box = byId('toast');
        box.textContent = message;
        box.className = 'toast show' + (error ? ' error' : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { box.className = 'toast'; }, 3300);
    }
    async function request(action, options = {}) {
        const headers = new Headers(options.headers || {});
        if (options.method === 'POST') headers.set('X-CSRF-Token', state.csrf);
        const response = await fetch(api + '?action=' + encodeURIComponent(action), { ...options, headers, credentials: 'same-origin' });
        const data = await response.json().catch(() => ({ ok: false, message: 'Phản hồi máy chủ không hợp lệ.' }));
        if (!response.ok || !data.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
        return data;
    }
    async function send(action, payload) {
        return request(action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    }
    async function refreshStatus() {
        const data = await request('status');
        state.csrf = data.csrf || '';
    }
    async function loadSites() {
        const data = await request('list');
        state.sites = Array.isArray(data.sites) ? data.sites : [];
        renderCategories();
        render();
    }
    function renderCategories() {
        const counts = new Map();
        state.sites.forEach(site => {
            const name = site.category || 'Khác';
            counts.set(name, (counts.get(name) || 0) + 1);
        });
        byId('allCount').textContent = state.sites.length;
        byId('pinnedCount').textContent = state.sites.filter(site => site.pinned).length;
        byId('categoryLinks').innerHTML = [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0], 'vi')).map(([name, count]) =>
            '<button class="nav-item" type="button" data-category="' + escapeHtml(name) + '"><span class="nav-icon category-mark">●</span><span>' + escapeHtml(name) + '</span><span class="nav-count">' + count + '</span></button>'
        ).join('');
        if (state.category !== 'all' && state.category !== 'pinned' && !counts.has(state.category)) state.category = 'all';
        document.querySelectorAll('[data-category]').forEach(button => button.classList.toggle('active', button.dataset.category === state.category));
    }
    function filteredSites() {
        const q = searchInput.value.trim().toLocaleLowerCase('vi');
        const result = state.sites.filter(site => {
            const category = state.category === 'all' || (state.category === 'pinned' ? !!site.pinned : (site.category || 'Khác') === state.category);
            const text = [site.name, site.description, site.url, site.category].join(' ').toLocaleLowerCase('vi');
            return category && (!q || text.includes(q));
        });
        if (byId('sortSelect').value === 'name') result.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'vi'));
        else result.sort((a, b) => Number(!!b.pinned) - Number(!!a.pinned) || String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
        return result;
    }
    function cardMarkup(site) {
        const url = safeUrl(site.url);
        const category = site.category || 'Khác';
        const title = escapeHtml(site.name);
        const image = site.image && /^uploads\/[a-zA-Z0-9-]+\.(jpg|png|webp|gif)$/.test(site.image) ? site.image : autoThumbnail(url);
        const initial = escapeHtml((site.name || 'W').trim().slice(0, 1).toLocaleUpperCase('vi'));
        const buttons = '<div class="card-admin"><button type="button" data-action="edit" aria-label="Sửa ' + title + '" title="Sửa">✎</button><button type="button" data-action="delete" aria-label="Xóa ' + title + '" title="Xóa">×</button></div>';
        return '<article class="site-card" data-id="' + escapeHtml(site.id) + '">' +
            '<div class="card-image-wrap ' + coverColor(category) + '"><div class="card-pattern"></div><span class="card-initial">' + initial + '</span>' +
            (image ? '<img class="card-image" src="' + escapeHtml(image) + '" alt="" loading="lazy">' : '') +
            '<a class="card-image-link" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" aria-label="Mở ' + title + '"></a>' +
            '<span class="category-pill">' + escapeHtml(category) + '</span>' + (site.pinned ? '<span class="pin-pill" title="Đã ghim">✦</span>' : '') + buttons + '</div>' +
            '<div class="site-card-body"><div class="card-type">WEB APPLICATION <span>·</span> ' + escapeHtml(host(url)) + '</div>' +
            '<h3><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + title + '</a></h3><p>' + escapeHtml(site.description || 'Một ứng dụng trong bộ sưu tập của tôi.') + '</p>' +
            '<div class="card-footer"><span class="card-domain">' + escapeHtml(host(url)) + '</span><a class="card-open" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" aria-label="Mở ' + title + '">↗</a></div></div></article>';
    }
    function render() {
        const total = state.sites.length;
        const categories = new Set(state.sites.map(site => site.category || 'Khác'));
        byId('totalCount').textContent = total;
        byId('categoryCount').textContent = categories.size;
        byId('featuredCount').textContent = state.sites.filter(site => site.pinned).length;
        const title = state.category === 'all' ? 'Tất cả website' : state.category === 'pinned' ? 'Website đã ghim' : state.category;
        byId('sectionTitle').textContent = title;
        byId('breadcrumbCurrent').textContent = title;
        byId('sectionDescription').textContent = state.category === 'pinned' ? 'Những ứng dụng được ưu tiên trong bộ sưu tập.' : 'Các dự án và công cụ trong một không gian chung.';
        const list = filteredSites();
        byId('resultCount').textContent = list.length + ' kết quả';
        grid.innerHTML = list.map(cardMarkup).join('');
        const empty = byId('emptyState');
        empty.hidden = list.length > 0;
        if (list.length === 0) {
            const searching = searchInput.value.trim() !== '' || state.category !== 'all';
            byId('emptyTitle').textContent = searching ? 'Không tìm thấy website' : 'Bộ sưu tập đang trống';
            byId('emptyText').textContent = searching ? 'Hãy thử một từ khóa hoặc danh mục khác.' : 'Thêm website đầu tiên để bắt đầu bộ sưu tập.';
            byId('emptyAddBtn').hidden = searching;
            byId('emptyAddBtn').textContent = '＋ Thêm website';
        }
    }
    function showModal(modal) {
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }
    function hideModal(modal) {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }
    function clearPreviewUrl() {
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = '';
    }
    function setPreview(src) {
        const preview = byId('imagePreview');
        preview.replaceChildren();
        if (!src) { const span = document.createElement('span'); span.textContent = 'Thumbnail tự động từ URL'; preview.append(span); return; }
        const image = document.createElement('img');
        image.src = src;
        image.alt = 'Ảnh xem trước';
        image.onerror = () => { preview.replaceChildren(); const span = document.createElement('span'); span.textContent = 'Không tải được thumbnail'; preview.append(span); };
        preview.append(image);
    }
    function openSite(site = null) {
        siteForm.reset();
        clearPreviewUrl();
        byId('siteId').value = site ? site.id : '';
        byId('siteName').value = site ? site.name : '';
        byId('siteUrl').value = site ? site.url : '';
        byId('siteCategory').value = site ? site.category || '' : '';
        byId('siteDescription').value = site ? site.description || '' : '';
        byId('siteImage').value = site ? site.image || '' : '';
        byId('sitePinned').checked = site ? !!site.pinned : false;
        byId('imageFile').value = '';
        byId('modalTitle').textContent = site ? 'Chỉnh sửa website' : 'Thêm website';
        setPreview(site ? site.image || autoThumbnail(site.url) : '');
        showModal(siteModal);
        setTimeout(() => byId('siteName').focus(), 50);
    }

    byId('categoryNav').addEventListener('click', event => {
        const button = event.target.closest('[data-category]');
        if (!button) return;
        state.category = button.dataset.category;
        document.querySelectorAll('[data-category]').forEach(item => item.classList.toggle('active', item === button));
        render();
        byId('sidebar').classList.remove('open');
        byId('sidebarShade').hidden = true;
        if (window.innerWidth < 900) byId('collection').scrollIntoView({ behavior: 'smooth' });
    });
    searchInput.addEventListener('input', render);
    byId('sortSelect').addEventListener('change', render);
    grid.addEventListener('click', async event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const site = state.sites.find(item => item.id === button.closest('[data-id]').dataset.id);
        if (!site) return;
        if (button.dataset.action === 'edit') { openSite(site); return; }
        if (button.dataset.action === 'delete') {
            if (!window.confirm('Xóa website “' + site.name + '”?')) return;
            try { await send('delete', { id: site.id }); await loadSites(); toast('Đã xóa website.'); }
            catch (error) { toast(error.message, true); }
        }
    });
    siteForm.addEventListener('submit', async event => {
        event.preventDefault();
        const button = byId('saveBtn');
        button.disabled = true;
        try {
            const url = normalizeUrl(byId('siteUrl').value);
            const parsed = new URL(url);
            if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('URL website không hợp lệ.');
            let image = byId('siteImage').value;
            const file = byId('imageFile').files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) throw new Error('Ảnh phải nhỏ hơn 5 MB.');
                const body = new FormData();
                body.append('image', file);
                image = (await request('upload', { method: 'POST', body })).path;
            }
            const payload = {
                id: byId('siteId').value, name: byId('siteName').value.trim(), url,
                category: byId('siteCategory').value.trim() || 'Khác',
                description: byId('siteDescription').value.trim(), image,
                pinned: byId('sitePinned').checked
            };
            await send('save', payload);
            hideModal(siteModal);
            clearPreviewUrl();
            await loadSites();
            toast(payload.id ? 'Đã cập nhật website.' : 'Đã thêm website.');
        } catch (error) { toast(error.message || 'Không thể lưu website.', true); }
        finally { button.disabled = false; }
    });
    byId('imageFile').addEventListener('change', event => {
        clearPreviewUrl();
        const file = event.target.files[0];
        if (!file) { setPreview(byId('siteImage').value || autoThumbnail(normalizeUrl(byId('siteUrl').value))); return; }
        if (file.size > 5 * 1024 * 1024) { event.target.value = ''; toast('Ảnh phải nhỏ hơn 5 MB.', true); return; }
        state.previewUrl = URL.createObjectURL(file);
        setPreview(state.previewUrl);
    });
    byId('removeImageBtn').addEventListener('click', () => {
        clearPreviewUrl(); byId('siteImage').value = ''; byId('imageFile').value = ''; setPreview(autoThumbnail(normalizeUrl(byId('siteUrl').value)));
    });
    byId('siteUrl').addEventListener('input', () => {
        if (byId('siteImage').value || byId('imageFile').files.length) return;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(() => setPreview(autoThumbnail(normalizeUrl(byId('siteUrl').value))), 600);
    });
    grid.addEventListener('error', event => {
        if (event.target.matches('.card-image')) event.target.remove();
    }, true);
    [byId('addSiteBtn'), byId('heroAddBtn'), byId('emptyAddBtn')].forEach(button => button.addEventListener('click', () => openSite()));
    [byId('closeModalBtn'), byId('cancelBtn')].forEach(button => button.addEventListener('click', () => { hideModal(siteModal); clearPreviewUrl(); }));
    siteModal.addEventListener('click', event => { if (event.target === siteModal) hideModal(siteModal); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { hideModal(siteModal); byId('sidebar').classList.remove('open'); byId('sidebarShade').hidden = true; }
        if (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) && siteModal.hidden) { event.preventDefault(); searchInput.focus(); }
    });
    byId('menuBtn').addEventListener('click', () => { byId('sidebar').classList.add('open'); byId('sidebarShade').hidden = false; });
    byId('sidebarShade').addEventListener('click', () => { byId('sidebar').classList.remove('open'); byId('sidebarShade').hidden = true; });
    byId('themeBtn').addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem('webhub-theme', theme); } catch (_) {}
    });
    byId('todayLabel').textContent = new Intl.DateTimeFormat('vi-VN', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());
    refreshStatus().then(loadSites).catch(error => toast(error.message, true));
})();
