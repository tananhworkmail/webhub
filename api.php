<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$action = cleanText($_GET['action'] ?? '', 40);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'sites' && $method === 'GET') {
    $sites = readSites();
    usort($sites, static function ($a, $b) {
        $pin = (int)!empty($b['pinned']) <=> (int)!empty($a['pinned']);
        return $pin ?: strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });
    jsonResponse(['ok' => true, 'sites' => $sites]);
}

if ($action === 'groups' && $method === 'GET') {
    $groups = readGroups();
    $counts = [];
    foreach (readSites() as $site) {
        $groupId = (string)($site['group_id'] ?? '');
        if ($groupId !== '') $counts[$groupId] = ($counts[$groupId] ?? 0) + 1;
    }
    foreach ($groups as &$group) $group['web_count'] = $counts[(string)($group['id'] ?? '')] ?? 0;
    unset($group);
    usort($groups, static fn($a, $b) => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    jsonResponse(['ok' => true, 'groups' => $groups]);
}

if ($action === 'health' && $method === 'GET') {
    $sites = readSites();
    $cache = readStatusCache();
    $refresh = ($_GET['refresh'] ?? '') === '1';
    if ($refresh || !statusCacheIsFresh($cache, $sites)) {
        $cache = ['checked_at' => date(DATE_ATOM), 'items' => checkSiteHealth($sites)];
        writeStatusCache($cache);
    }
    jsonResponse([
        'ok' => true,
        'checked_at' => $cache['checked_at'] ?? null,
        'statuses' => $cache['items'] ?? [],
    ]);
}

startSession();
if ($action === 'status' && $method === 'GET') {
    jsonResponse(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}
if ($method !== 'POST') jsonResponse(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
if (!hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    jsonResponse(['ok' => false, 'message' => 'Phiên làm việc đã hết hạn. Hãy tải lại trang.'], 403);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) $input = [];

if ($action === 'upload') {
    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        jsonResponse(['ok' => false, 'message' => 'Chưa chọn ảnh.'], 400);
    }
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) jsonResponse(['ok' => false, 'message' => 'Upload ảnh thất bại.'], 400);
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES || ($file['size'] ?? 0) <= 0) jsonResponse(['ok' => false, 'message' => 'Ảnh phải nhỏ hơn 5 MB.'], 400);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        jsonResponse(['ok' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP hoặc GIF.'], 400);
    }
    ensureStorage();
    $name = date('YmdHis') . '-' . generateId() . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) jsonResponse(['ok' => false, 'message' => 'Không thể lưu ảnh.'], 500);
    jsonResponse(['ok' => true, 'path' => 'uploads/' . $name]);
}

if ($action === 'site_save') {
    $id = cleanText($input['id'] ?? '', 64);
    $name = cleanText($input['name'] ?? '', 100);
    $url = normalizeUrl(cleanText($input['url'] ?? '', 2048));
    $description = cleanText($input['description'] ?? '', 500);
    $groupId = cleanText($input['group_id'] ?? '', 64);
    $image = cleanText($input['image'] ?? '', 500);
    $pinned = !empty($input['pinned']);

    if ($name === '') jsonResponse(['ok' => false, 'message' => 'Vui lòng nhập tên website.'], 422);
    if (!validHttpUrl($url)) jsonResponse(['ok' => false, 'message' => 'URL website không hợp lệ.'], 422);
    if (!uploadedImagePath($image)) jsonResponse(['ok' => false, 'message' => 'Ảnh đại diện không hợp lệ.'], 422);
    if ($groupId !== '' && !array_filter(readGroups(), static fn($group) => ($group['id'] ?? '') === $groupId)) {
        jsonResponse(['ok' => false, 'message' => 'Nhóm website không tồn tại.'], 422);
    }

    try {
        $oldImage = updateSites(static function (&$sites) use ($id, $name, $url, $description, $groupId, $image, $pinned) {
            $now = date(DATE_ATOM);
            $payload = ['name' => $name, 'url' => $url, 'description' => $description, 'group_id' => $groupId, 'image' => $image, 'pinned' => $pinned, 'updated_at' => $now];
            if ($id !== '') {
                foreach ($sites as &$site) {
                    if (($site['id'] ?? '') !== $id) continue;
                    $old = (string)($site['image'] ?? '');
                    $site = array_merge($site, $payload);
                    unset($site['category']);
                    return $old !== $image ? $old : '';
                }
                throw new DomainException('Không tìm thấy website cần sửa.');
            }
            $sites[] = array_merge(['id' => generateId(), 'created_at' => $now], $payload);
            return '';
        });
        deleteUploadedImage($oldImage);
        writeStatusCache([]);
        jsonResponse(['ok' => true]);
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 404);
    } catch (Throwable) {
        jsonResponse(['ok' => false, 'message' => 'Không thể lưu website. Kiểm tra quyền ghi data/.'], 500);
    }
}

if ($action === 'site_delete') {
    $id = cleanText($input['id'] ?? '', 64);
    try {
        $image = updateSites(static function (&$sites) use ($id) {
            foreach ($sites as $index => $site) {
                if (($site['id'] ?? '') !== $id) continue;
                array_splice($sites, $index, 1);
                return (string)($site['image'] ?? '');
            }
            throw new DomainException('Không tìm thấy website.');
        });
        deleteUploadedImage($image);
        writeStatusCache([]);
        jsonResponse(['ok' => true]);
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 404);
    } catch (Throwable) {
        jsonResponse(['ok' => false, 'message' => 'Không thể xóa website.'], 500);
    }
}

if ($action === 'group_save') {
    $id = cleanText($input['id'] ?? '', 64);
    $name = cleanText($input['name'] ?? '', 80);
    if ($name === '') jsonResponse(['ok' => false, 'message' => 'Vui lòng nhập tên nhóm.'], 422);
    try {
        updateGroups(static function (&$groups) use ($id, $name) {
            foreach ($groups as $group) {
                if (strcasecmp((string)($group['name'] ?? ''), $name) === 0 && ($group['id'] ?? '') !== $id) {
                    throw new InvalidArgumentException('Tên nhóm đã tồn tại.');
                }
            }
            $now = date(DATE_ATOM);
            if ($id !== '') {
                foreach ($groups as &$group) {
                    if (($group['id'] ?? '') !== $id) continue;
                    $group['name'] = $name;
                    $group['updated_at'] = $now;
                    return;
                }
                throw new DomainException('Không tìm thấy nhóm cần sửa.');
            }
            $groups[] = ['id' => generateId(), 'name' => $name, 'created_at' => $now, 'updated_at' => $now];
        });
        jsonResponse(['ok' => true]);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 422);
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 404);
    } catch (Throwable) {
        jsonResponse(['ok' => false, 'message' => 'Không thể lưu nhóm.'], 500);
    }
}

if ($action === 'group_delete') {
    $id = cleanText($input['id'] ?? '', 64);
    try {
        updateGroups(static function (&$groups) use ($id) {
            foreach ($groups as $index => $group) {
                if (($group['id'] ?? '') !== $id) continue;
                array_splice($groups, $index, 1);
                return;
            }
            throw new DomainException('Không tìm thấy nhóm.');
        });
        $moved = updateSites(static function (&$sites) use ($id) {
            $count = 0;
            foreach ($sites as &$site) {
                if (($site['group_id'] ?? '') !== $id) continue;
                $site['group_id'] = '';
                $site['updated_at'] = date(DATE_ATOM);
                $count++;
            }
            return $count;
        });
        jsonResponse(['ok' => true, 'moved' => $moved]);
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 404);
    } catch (Throwable) {
        jsonResponse(['ok' => false, 'message' => 'Không thể xóa nhóm.'], 500);
    }
}

jsonResponse(['ok' => false, 'message' => 'Thao tác không tồn tại.'], 404);
