(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    const page = document.body.dataset.page;
    const state = { sites: [], groups: [], statuses: {}, checkedAt: '', csrf: '', previewUrl: '' };
    let toastTimer;
    let previewTimer;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
    const normalizeUrl = value => /^https?:\/\//i.test(value.trim()) ? value.trim() : 'https://' + value.trim();
    const safeUrl = value => {
        try {
            const url = new URL(value);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
        } catch (_) { return '#'; }
    };
    const host = value => {
        try { return new URL(value).hostname.replace(/^www\./, ''); }
        catch (_) { return value; }
    };
    const thumbnail = value => {
        const url = safeUrl(value);
        return url === '#' ? '' : 'https://s0.wp.com/mshots/v1/' + encodeURIComponent(url) + '?w=900&h=560';
    };
    const groupById = id => state.groups.find(group => group.id === id);
    const groupName = site => groupById(site.group_id)?.name || site.category || 'Chưa phân nhóm';
    const formatDate = value => value ? new Date(value).toLocaleString('vi-VN') : '-';

    function showToast(message, isError = false) {
        const toast = $('toast');
        toast.textContent = message;
        toast.className = 'toast show' + (isError ? ' error' : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.className = 'toast'; }, 3000);
    }

    async function request(action, options = {}) {
        const { query, ...fetchOptions } = options;
        const headers = new Headers(fetchOptions.headers || {});
        if (fetchOptions.method === 'POST') headers.set('X-CSRF-Token', state.csrf);
        const params = query ? '&' + new URLSearchParams(query).toString() : '';
        const response = await fetch('api.php?action=' + encodeURIComponent(action) + params, {
            ...fetchOptions, headers, credentials: 'same-origin'
        });
        const data = await response.json().catch(() => ({ ok: false, message: 'Phản hồi máy chủ không hợp lệ.' }));
        if (!response.ok || !data.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
        return data;
    }

    const post = (action, payload) => request(action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    async function loadData() {
        const status = await request('status');
        state.csrf = status.csrf || '';
        const [siteData, groupData] = await Promise.all([request('sites'), request('groups')]);
        state.sites = siteData.sites || [];
        state.groups = groupData.groups || [];
    }

    async function loadHealth(refresh = false) {
        const data = await request('health', { query: refresh ? { refresh: '1' } : undefined });
        state.statuses = data.statuses || {};
        state.checkedAt = data.checked_at || '';
    }

    function bindCommon() {
        $('themeBtn')?.addEventListener('click', () => {
            const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = theme;
            try { localStorage.setItem('webhub-theme', theme); } catch (_) {}
        });
        document.addEventListener('error', event => {
            if (event.target.matches('img.auto-thumbnail')) event.target.remove();
        }, true);
    }

    function healthOf(site) {
        const checkedUrl = safeUrl(site.url);
        const isHttps = checkedUrl !== '#' && checkedUrl.startsWith('https://');
        return state.statuses[site.id] || {
            status: 'Checking...',
            responseTimeMs: null,
            httpStatusCode: null,
            checkedAt: '',
            error: '',
            url: site.url,
            host: host(site.url),
            dnsStatus: 'Unknown',
            sslStatus: isHttps ? 'Unknown' : 'Not HTTPS',
            sslDaysLeft: null
        };
    }

    function statusLabel(detail) {
        if (detail.status === 'Online') return 'Online';
        if (detail.status === 'Checking...') return 'Đang kiểm tra';
        return 'Offline';
    }

    function statusClass(detail) {
        if (detail.status === 'Online') return 'online';
        if (detail.status === 'Checking...') return 'checking';
        return 'offline';
    }

    function fillGroupOptions(select, includeAll = false) {
        const options = state.groups.map(group =>
            '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + '</option>'
        ).join('');
        select.innerHTML = includeAll
            ? '<option value="all">Tất cả nhóm</option><option value="ungrouped">Chưa phân nhóm</option>' + options
            : '<option value="">Chưa phân nhóm</option>' + options;
    }

    function allSitesFiltered() {
        const query = $('allSearch').value.trim().toLocaleLowerCase('vi');
        const group = $('allGroupFilter').value;
        return state.sites.filter(site => {
            const matchesGroup = group === 'all' || (group === 'ungrouped' ? !site.group_id : site.group_id === group);
            const haystack = [site.name, site.url, site.description, groupName(site)].join(' ').toLocaleLowerCase('vi');
            return matchesGroup && (!query || haystack.includes(query));
        });
    }

    function publicCard(site) {
        const detail = healthOf(site);
        const online = detail.status === 'Online';
        const url = safeUrl(site.url);
        const image = site.image || thumbnail(url);
        const tag = '<span class="status-tag ' + statusClass(detail) + '"><i></i>' + statusLabel(detail) + '</span>';
        const response = detail.responseTimeMs == null ? '' : '<span class="response-time">' + Number(detail.responseTimeMs) + ' ms</span>';
        const inner = '<div class="public-cover"><span class="cover-fallback">' + escapeHtml((site.name || 'W').slice(0, 1).toUpperCase()) + '</span>' +
            (image ? '<img class="auto-thumbnail" src="' + escapeHtml(image) + '" alt="">' : '') +
            (site.pinned ? '<span class="pin-badge">★</span>' : '') + '</div>' +
            '<div class="public-card-body"><span class="group-label">' + escapeHtml(groupName(site)) + '</span>' +
            '<h2>' + escapeHtml(site.name) + '</h2><p>' + escapeHtml(site.description || host(url)) + '</p>' +
            '<div class="public-card-foot">' + tag + response + '</div></div>';
        return online
            ? '<a class="public-card" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + inner + '</a>'
            : '<article class="public-card disabled" aria-disabled="true" title="' + escapeHtml(detail.error || 'Website đang offline') + '">' + inner + '</article>';
    }

    function renderAllSites() {
        const sites = allSitesFiltered();
        $('publicSiteGrid').innerHTML = sites.map(publicCard).join('');
        $('publicSiteEmpty').hidden = sites.length > 0;
        $('allCheckedAt').textContent = state.checkedAt ? 'Kiểm tra: ' + formatDate(state.checkedAt) : 'Đang kiểm tra...';
    }

    async function refreshAll() {
        const button = $('refreshAllBtn');
        button.disabled = true;
        button.textContent = 'Đang kiểm tra...';
        state.statuses = {};
        renderAllSites();
        try {
            await loadHealth(true);
            renderAllSites();
        } catch (error) { showToast(error.message, true); }
        finally {
            button.disabled = false;
            button.textContent = '↻ Kiểm tra lại';
        }
    }

    async function initAllSites() {
        fillGroupOptions($('allGroupFilter'), true);
        const selectedGroup = new URLSearchParams(location.search).get('group');
        if (selectedGroup && state.groups.some(group => group.id === selectedGroup)) $('allGroupFilter').value = selectedGroup;
        $('allSearch').addEventListener('input', renderAllSites);
        $('allGroupFilter').addEventListener('change', renderAllSites);
        $('refreshAllBtn').addEventListener('click', refreshAll);
        renderAllSites();
        try {
            await loadHealth(false);
            renderAllSites();
        } catch (error) { showToast(error.message, true); }
    }

    function dashboardRows() {
        return state.sites.map(site => ({ site, detail: healthOf(site) }));
    }

    function domainLabel(detail) {
        if (detail.dnsStatus === 'Failed') return 'DNS lỗi';
        if (detail.sslStatus === 'Expired') return 'SSL hết hạn';
        if (detail.sslStatus === 'Failed') return 'SSL lỗi';
        if (detail.sslStatus === 'Expiring Soon') return 'Còn ' + detail.sslDaysLeft + ' ngày';
        if (detail.sslStatus === 'OK' && detail.sslDaysLeft != null) return 'Còn ' + detail.sslDaysLeft + ' ngày';
        if (detail.sslStatus === 'OK') return 'SSL hợp lệ';
        return detail.sslStatus || '-';
    }

    function renderDashboard() {
        const rows = dashboardRows();
        const online = rows.filter(row => row.detail.status === 'Online' && !row.detail.error).length;
        const offline = rows.length - online;
        const responseRows = rows.filter(row => Number.isFinite(row.detail.responseTimeMs));
        const average = responseRows.length
            ? Math.round(responseRows.reduce((sum, row) => sum + row.detail.responseTimeMs, 0) / responseRows.length)
            : 0;
        const onlinePercent = rows.length ? Math.round(online / rows.length * 100) : 0;
        const offlinePercent = rows.length ? 100 - onlinePercent : 0;

        $('metricTotal').textContent = rows.length;
        $('metricOnline').textContent = online;
        $('metricOffline').textContent = offline;
        $('metricAverage').textContent = average;
        $('onlineBar').style.width = onlinePercent + '%';
        $('offlineBar').style.width = offlinePercent + '%';
        $('onlineBarCount').textContent = online;
        $('offlineBarCount').textContent = offline;
        $('onlinePercent').textContent = onlinePercent + '%';
        $('legendOnline').textContent = online;
        $('legendOffline').textContent = offline;
        $('statusDonut').style.setProperty('--online-percent', onlinePercent + '%');
        $('dashboardCheckedAt').textContent = state.checkedAt ? formatDate(state.checkedAt) : 'Đang kiểm tra...';

        const domainRows = rows.filter(row => row.detail.sslStatus !== 'Not HTTPS');
        $('dnsFailed').textContent = domainRows.filter(row => row.detail.dnsStatus === 'Failed').length;
        $('sslSoon').textContent = domainRows.filter(row => row.detail.sslStatus === 'Expiring Soon').length;
        $('sslFailed').textContent = domainRows.filter(row => ['Failed', 'Expired'].includes(row.detail.sslStatus)).length;
        const domainSorted = [...domainRows].sort((a, b) => {
            const weight = detail => detail.dnsStatus === 'Failed' || ['Failed', 'Expired'].includes(detail.sslStatus) ? 3 : detail.sslStatus === 'Expiring Soon' ? 2 : 0;
            return weight(b.detail) - weight(a.detail);
        });
        $('domainList').innerHTML = domainSorted.slice(0, 20).map(row => {
            const bad = row.detail.dnsStatus === 'Failed' || ['Failed', 'Expired'].includes(row.detail.sslStatus);
            const warn = row.detail.sslStatus === 'Expiring Soon';
            return '<div class="domain-row"><span><strong>' + escapeHtml(row.site.name) + '</strong><small>' + escapeHtml(row.detail.host || host(row.site.url)) + '</small></span>' +
                '<b class="domain-tag ' + (bad ? 'bad' : warn ? 'warn' : 'ok') + '">' + escapeHtml(domainLabel(row.detail)) + '</b></div>';
        }).join('');
        $('domainEmpty').hidden = domainSorted.length > 0;

        const problems = rows.filter(row => row.detail.status !== 'Online' || row.detail.error);
        $('problemRows').innerHTML = problems.map(row =>
            '<tr><td><span class="web-link">' + escapeHtml(row.site.name) + '</span></td><td>' +
            (row.detail.httpStatusCode || '-') + '</td><td><span class="error-text">' + escapeHtml(row.detail.error || 'Offline') +
            '</span></td><td>' + escapeHtml(formatDate(row.detail.checkedAt)) + '</td></tr>'
        ).join('');
        $('problemEmpty').hidden = problems.length > 0;

        const slow = [...responseRows].sort((a, b) => b.detail.responseTimeMs - a.detail.responseTimeMs).slice(0, 10);
        $('slowRows').innerHTML = slow.map(row =>
            '<tr><td><span class="web-link">' + escapeHtml(row.site.name) + '</span></td><td><span class="mini-status ' +
            statusClass(row.detail) + '">' + statusLabel(row.detail) + '</span></td><td><strong>' + row.detail.responseTimeMs + ' ms</strong></td></tr>'
        ).join('');
        $('slowEmpty').hidden = slow.length > 0;
    }

    async function refreshDashboard() {
        const button = $('refreshDashboardBtn');
        button.disabled = true;
        button.textContent = 'Đang kiểm tra...';
        state.statuses = {};
        renderDashboard();
        try {
            await loadHealth(true);
            renderDashboard();
        } catch (error) { showToast(error.message, true); }
        finally {
            button.disabled = false;
            button.textContent = '↻ Làm mới';
        }
    }

    async function initDashboard() {
        $('refreshDashboardBtn').addEventListener('click', refreshDashboard);
        renderDashboard();
        try {
            await loadHealth(false);
            renderDashboard();
        } catch (error) { showToast(error.message, true); }
    }

    function managerSitesFiltered() {
        const query = $('managerSearch').value.trim().toLocaleLowerCase('vi');
        return state.sites.filter(site => !query || [site.name, site.url, site.description, groupName(site)].join(' ').toLocaleLowerCase('vi').includes(query));
    }

    function renderManagerSites() {
        const sites = managerSitesFiltered();
        $('managerSiteCount').textContent = sites.length + '/' + state.sites.length;
        $('managerSiteEmpty').hidden = sites.length > 0;
        $('managerSiteRows').innerHTML = sites.map(site => {
            const image = site.image || thumbnail(site.url);
            return '<tr data-id="' + escapeHtml(site.id) + '"><td><span class="table-thumb"><b>' +
                escapeHtml((site.name || 'W').slice(0, 1).toUpperCase()) + '</b>' +
                (image ? '<img class="auto-thumbnail" src="' + escapeHtml(image) + '" alt="">' : '') + '</span></td>' +
                '<td><strong>' + escapeHtml(site.name) + '</strong>' + (site.pinned ? '<span class="pinned-mark">★</span>' : '') + '</td>' +
                '<td><a class="address-link" href="' + escapeHtml(safeUrl(site.url)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(site.url) + '</a></td>' +
                '<td>' + escapeHtml(groupName(site)) + '</td><td>' + escapeHtml(formatDate(site.updated_at)) + '</td>' +
                '<td><div class="row-actions"><button type="button" data-action="edit">Sửa</button><button class="danger-text" type="button" data-action="delete">Xóa</button></div></td></tr>';
        }).join('');
    }

    function resetSiteForm() {
        $('siteForm').reset();
        $('siteId').value = '';
        $('siteImage').value = '';
        $('imageFile').value = '';
        $('siteFormTitle').textContent = '＋ Thêm website mới';
        $('saveSiteBtn').textContent = 'Lưu website';
        clearPreviewUrl();
        setImagePreview('');
    }

    function editSite(site) {
        resetSiteForm();
        $('siteId').value = site.id;
        $('siteImage').value = site.image || '';
        $('siteName').value = site.name || '';
        $('siteGroup').value = site.group_id || '';
        $('siteUrl').value = site.url || '';
        $('siteDescription').value = site.description || '';
        $('sitePinned').checked = !!site.pinned;
        $('siteFormTitle').textContent = '✎ Chỉnh sửa website';
        $('saveSiteBtn').textContent = 'Cập nhật website';
        setImagePreview(site.image || thumbnail(site.url));
        $('siteForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('siteName').focus({ preventScroll: true });
    }

    function clearPreviewUrl() {
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = '';
    }

    function setImagePreview(src) {
        const preview = $('imagePreview');
        preview.replaceChildren();
        if (!src) {
            const text = document.createElement('span');
            text.textContent = 'Thumbnail từ URL';
            preview.append(text);
            return;
        }
        const image = document.createElement('img');
        image.src = src;
        image.alt = 'Ảnh xem trước';
        image.onerror = () => {
            preview.replaceChildren();
            const text = document.createElement('span');
            text.textContent = 'Không tải được thumbnail';
            preview.append(text);
        };
        preview.append(image);
    }

    async function reloadManagerData() {
        const [siteData, groupData] = await Promise.all([request('sites'), request('groups')]);
        state.sites = siteData.sites || [];
        state.groups = groupData.groups || [];
        fillGroupOptions($('siteGroup'));
        renderManagerSites();
        renderManagerGroups();
    }

    async function saveSite(event) {
        event.preventDefault();
        const button = $('saveSiteBtn');
        const editing = !!$('siteId').value;
        button.disabled = true;
        try {
            const url = normalizeUrl($('siteUrl').value);
            if (safeUrl(url) === '#') throw new Error('URL website không hợp lệ.');
            let image = $('siteImage').value;
            const file = $('imageFile').files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) throw new Error('Ảnh phải nhỏ hơn 5 MB.');
                const formData = new FormData();
                formData.append('image', file);
                image = (await request('upload', { method: 'POST', body: formData })).path;
            }
            await post('site_save', {
                id: $('siteId').value,
                name: $('siteName').value.trim(),
                url,
                description: $('siteDescription').value.trim(),
                group_id: $('siteGroup').value,
                image,
                pinned: $('sitePinned').checked
            });
            await reloadManagerData();
            resetSiteForm();
            showToast(editing ? 'Đã cập nhật website.' : 'Đã thêm website.');
        } catch (error) { showToast(error.message, true); }
        finally { button.disabled = false; }
    }

    function renderManagerGroups() {
        $('managerGroupCount').textContent = state.groups.length;
        $('managerGroupEmpty').hidden = state.groups.length > 0;
        $('managerGroupRows').innerHTML = state.groups.map(group =>
            '<tr data-id="' + escapeHtml(group.id) + '"><td><strong>' + escapeHtml(group.name) + '</strong></td>' +
            '<td><a class="address-link" href="index.php?group=' + encodeURIComponent(group.id) + '">' + Number(group.web_count || 0) + ' website</a></td>' +
            '<td>' + escapeHtml(formatDate(group.created_at)) + '</td><td><div class="row-actions">' +
            '<button type="button" data-action="edit">Sửa tên</button><button class="danger-text" type="button" data-action="delete">Xóa</button></div></td></tr>'
        ).join('');
    }

    function resetGroupForm() {
        $('groupForm').reset();
        $('groupId').value = '';
        $('groupFormTitle').textContent = '＋ Thêm nhóm website';
        $('saveGroupBtn').textContent = 'Lưu nhóm';
    }

    function switchManagerTab(tab) {
        const groups = tab === 'groups';
        $('siteManagerPanel').hidden = groups;
        $('groupManagerPanel').hidden = !groups;
        document.querySelectorAll('[data-manager-tab]').forEach(button => button.classList.toggle('active', button.dataset.managerTab === tab));
        const url = new URL(location.href);
        if (groups) url.searchParams.set('tab', 'groups'); else url.searchParams.delete('tab');
        history.replaceState(null, '', url);
    }

    function bindManager() {
        fillGroupOptions($('siteGroup'));
        renderManagerSites();
        renderManagerGroups();
        document.querySelectorAll('[data-manager-tab]').forEach(button => button.addEventListener('click', () => switchManagerTab(button.dataset.managerTab)));
        $('managerSearch').addEventListener('input', renderManagerSites);
        $('resetSiteBtn').addEventListener('click', resetSiteForm);
        $('siteForm').addEventListener('submit', saveSite);
        $('managerSiteRows').addEventListener('click', async event => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            const site = state.sites.find(item => item.id === button.closest('[data-id]').dataset.id);
            if (!site) return;
            if (button.dataset.action === 'edit') return editSite(site);
            if (!confirm('Xóa website “' + site.name + '”?')) return;
            try {
                await post('site_delete', { id: site.id });
                await reloadManagerData();
                if ($('siteId').value === site.id) resetSiteForm();
                showToast('Đã xóa website.');
            } catch (error) { showToast(error.message, true); }
        });
        $('imageFile').addEventListener('change', event => {
            clearPreviewUrl();
            const file = event.target.files[0];
            if (!file) return setImagePreview($('siteImage').value || thumbnail(normalizeUrl($('siteUrl').value)));
            if (file.size > 5 * 1024 * 1024) {
                event.target.value = '';
                return showToast('Ảnh phải nhỏ hơn 5 MB.', true);
            }
            state.previewUrl = URL.createObjectURL(file);
            setImagePreview(state.previewUrl);
        });
        $('removeImageBtn').addEventListener('click', () => {
            clearPreviewUrl();
            $('siteImage').value = '';
            $('imageFile').value = '';
            setImagePreview(thumbnail(normalizeUrl($('siteUrl').value)));
        });
        $('siteUrl').addEventListener('input', () => {
            if ($('siteImage').value || $('imageFile').files.length) return;
            clearTimeout(previewTimer);
            previewTimer = setTimeout(() => setImagePreview(thumbnail(normalizeUrl($('siteUrl').value))), 500);
        });

        $('resetGroupBtn').addEventListener('click', resetGroupForm);
        $('groupForm').addEventListener('submit', async event => {
            event.preventDefault();
            const button = $('saveGroupBtn');
            const editing = !!$('groupId').value;
            button.disabled = true;
            try {
                await post('group_save', { id: $('groupId').value, name: $('groupName').value.trim() });
                await reloadManagerData();
                resetGroupForm();
                showToast(editing ? 'Đã đổi tên nhóm.' : 'Đã thêm nhóm.');
            } catch (error) { showToast(error.message, true); }
            finally { button.disabled = false; }
        });
        $('managerGroupRows').addEventListener('click', async event => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            const group = state.groups.find(item => item.id === button.closest('[data-id]').dataset.id);
            if (!group) return;
            if (button.dataset.action === 'edit') {
                $('groupId').value = group.id;
                $('groupName').value = group.name;
                $('groupFormTitle').textContent = '✎ Sửa tên nhóm';
                $('saveGroupBtn').textContent = 'Cập nhật nhóm';
                $('groupName').focus();
                return;
            }
            const note = group.web_count ? ' Các website trong nhóm sẽ chuyển về “Chưa phân nhóm”.' : '';
            if (!confirm('Xóa nhóm “' + group.name + '”?' + note)) return;
            try {
                await post('group_delete', { id: group.id });
                await reloadManagerData();
                if ($('groupId').value === group.id) resetGroupForm();
                showToast('Đã xóa nhóm.');
            } catch (error) { showToast(error.message, true); }
        });
        switchManagerTab(new URLSearchParams(location.search).get('tab') === 'groups' ? 'groups' : 'sites');
    }

    async function init() {
        bindCommon();
        await loadData();
        if (page === 'all') await initAllSites();
        if (page === 'dashboard') await initDashboard();
        if (page === 'manager') bindManager();
    }

    init().catch(error => showToast(error.message, true));
})();
