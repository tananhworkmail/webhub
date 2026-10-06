<?php
declare(strict_types=1);

const APP_NAME = 'Web Hub';
const SITE_FILE = __DIR__ . '/data/sites.json';
const GROUP_FILE = __DIR__ . '/data/groups.json';
const STATUS_FILE = __DIR__ . '/data/status.json';
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function ensureStorage(): void
{
    if (!is_dir(dirname(SITE_FILE))) mkdir(dirname(SITE_FILE), 0755, true);
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    foreach ([SITE_FILE, GROUP_FILE] as $file) {
        if (!file_exists($file)) file_put_contents($file, '[]', LOCK_EX);
    }
    if (!file_exists(STATUS_FILE)) file_put_contents(STATUS_FILE, '{}', LOCK_EX);
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('webhub_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readCollection(string $file): array
{
    ensureStorage();
    $fp = fopen($file, 'rb');
    if (!$fp || !flock($fp, LOCK_SH)) return [];
    $data = json_decode(stream_get_contents($fp) ?: '[]', true);
    flock($fp, LOCK_UN);
    fclose($fp);
    return is_array($data) && array_is_list($data) ? $data : [];
}

function updateCollection(string $file, callable $change): mixed
{
    ensureStorage();
    $fp = fopen($file, 'c+');
    if (!$fp) throw new RuntimeException('Không thể mở dữ liệu.');
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Không thể khóa dữ liệu.');
    }
    try {
        rewind($fp);
        $items = json_decode(stream_get_contents($fp) ?: '[]', true);
        if (!is_array($items) || !array_is_list($items)) throw new RuntimeException('Dữ liệu không hợp lệ.');
        $result = $change($items);
        $json = json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        rewind($fp);
        if (!ftruncate($fp, 0) || fwrite($fp, $json) !== strlen($json) || !fflush($fp)) {
            throw new RuntimeException('Không thể ghi dữ liệu.');
        }
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function readSites(): array { return readCollection(SITE_FILE); }
function readGroups(): array { return readCollection(GROUP_FILE); }
function updateSites(callable $change): mixed { return updateCollection(SITE_FILE, $change); }
function updateGroups(callable $change): mixed { return updateCollection(GROUP_FILE, $change); }

function cleanText(mixed $value, int $max = 255): string
{
    $value = trim(is_scalar($value) ? (string)$value : '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function normalizeUrl(string $url): string
{
    $url = trim($url);
    return $url !== '' && !preg_match('~^https?://~i', $url) ? 'https://' . $url : $url;
}

function validHttpUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    return isset($parts['host'], $parts['scheme'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        && !isset($parts['user']) && !isset($parts['pass']);
}

function uploadedImagePath(string $path): bool
{
    if ($path === '') return true;
    return preg_match('~^uploads/[a-zA-Z0-9-]+\.(?:jpg|png|webp|gif)$~', $path) === 1
        && is_file(__DIR__ . '/' . $path);
}

function deleteUploadedImage(?string $path): void
{
    if (!$path || !preg_match('~^uploads/[a-zA-Z0-9-]+\.(?:jpg|png|webp|gif)$~', $path)) return;
    $real = realpath(__DIR__ . '/' . $path);
    $root = realpath(UPLOAD_DIR);
    if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) @unlink($real);
}

function generateId(): string { return bin2hex(random_bytes(8)); }

function readStatusCache(): array
{
    ensureStorage();
    $data = json_decode(file_get_contents(STATUS_FILE) ?: '{}', true);
    return is_array($data) ? $data : [];
}

function writeStatusCache(array $data): bool
{
    ensureStorage();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) && file_put_contents(STATUS_FILE, $json, LOCK_EX) !== false;
}

function statusCacheIsFresh(array $cache, array $sites, int $seconds = 120): bool
{
    $checkedAt = strtotime((string)($cache['checked_at'] ?? ''));
    $items = $cache['items'] ?? null;
    if (!$checkedAt || $checkedAt < time() - $seconds || !is_array($items) || count($items) !== count($sites)) return false;
    foreach ($sites as $site) {
        $id = (string)($site['id'] ?? '');
        if ($id === '' || !isset($items[$id]) || ($items[$id]['url'] ?? '') !== ($site['url'] ?? '')) return false;
    }
    return true;
}

function emptyHealth(string $url): array
{
    return [
        'status' => 'Offline',
        'responseTimeMs' => null,
        'httpStatusCode' => null,
        'checkedAt' => date(DATE_ATOM),
        'error' => 'Không kiểm tra được',
        'url' => $url,
        'host' => parse_url($url, PHP_URL_HOST) ?: '',
        'dnsStatus' => 'Unknown',
        'sslStatus' => strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https' ? 'Unknown' : 'Not HTTPS',
        'sslDaysLeft' => null,
    ];
}

function certificateExpiry(array $certInfo): ?int
{
    foreach ($certInfo as $certificate) {
        if (!is_array($certificate)) continue;
        foreach (['Expire date', 'Not After', 'Expire Date'] as $key) {
            if (!empty($certificate[$key])) {
                $time = strtotime((string)$certificate[$key]);
                if ($time) return $time;
            }
        }
    }
    return null;
}

function finishCurlHealth($handle, string $url): array
{
    $result = emptyHealth($url);
    $info = curl_getinfo($handle);
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    $code = (int)($info['http_code'] ?? 0);
    $result['responseTimeMs'] = isset($info['total_time']) ? (int)round((float)$info['total_time'] * 1000) : null;
    $result['httpStatusCode'] = $code ?: null;
    $result['checkedAt'] = date(DATE_ATOM);
    $result['dnsStatus'] = $errno === 6 ? 'Failed' : 'OK';
    $result['status'] = !$errno && $code >= 100 && $code < 500 ? 'Online' : 'Offline';
    $result['error'] = $errno
        ? ($error ?: 'Kết nối thất bại')
        : ($code === 0 ? 'Không nhận được phản hồi' : ($code >= 400 ? 'HTTP ' . $code : ''));

    if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
        $sslErrors = [35, 51, 58, 60, 64, 66, 77, 80, 82, 83, 90];
        if (in_array($errno, $sslErrors, true)) {
            $result['sslStatus'] = 'Failed';
        } else {
            $expiry = certificateExpiry($info['certinfo'] ?? []);
            if ($expiry) {
                $days = (int)floor(($expiry - time()) / 86400);
                $result['sslDaysLeft'] = $days;
                $result['sslStatus'] = $days < 0 ? 'Expired' : ($days <= 30 ? 'Expiring Soon' : 'OK');
            } else {
                $result['sslStatus'] = $result['status'] === 'Online' ? 'OK' : 'Unknown';
            }
        }
    }
    return $result;
}

function checkSitesWithCurl(array $sites): array
{
    $multi = curl_multi_init();
    $handles = [];
    foreach (array_slice($sites, 0, 100) as $site) {
        $id = (string)($site['id'] ?? '');
        $url = normalizeUrl((string)($site['url'] ?? ''));
        if ($id === '' || !validHttpUrl($url)) continue;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            // Some shared hosts (including InfinityFree) close HEAD requests
            // without an HTTP status even though a normal browser GET works.
            CURLOPT_HTTPGET => true,
            CURLOPT_RANGE => '0-4095',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 9,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WebHub/1.0; +status-check)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CERTINFO => true,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[$id] = ['handle' => $handle, 'url' => $url];
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 1.0);
    } while ($running && $status === CURLM_OK);

    $result = [];
    foreach ($handles as $id => $item) {
        $result[$id] = finishCurlHealth($item['handle'], $item['url']);
        curl_multi_remove_handle($multi, $item['handle']);
    }
    curl_multi_close($multi);
    return $result;
}

function checkSitesWithSequentialCurl(array $sites): array
{
    $result = [];
    foreach (array_slice($sites, 0, 100) as $site) {
        $id = (string)($site['id'] ?? '');
        $url = normalizeUrl((string)($site['url'] ?? ''));
        if ($id === '' || !validHttpUrl($url)) continue;

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_RANGE => '0-4095',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WebHub/1.0; +status-check)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CERTINFO => true,
        ]);
        curl_exec($handle);
        $result[$id] = finishCurlHealth($handle, $url);
    }
    return $result;
}

function checkSiteWithStream(string $url): array
{
    $result = emptyHealth($url);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 3,
            'header' => "Range: bytes=0-4095\r\nUser-Agent: Mozilla/5.0 (compatible; WebHub/1.0; +status-check)\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'capture_peer_cert' => true],
    ]);
    if (function_exists('error_clear_last')) error_clear_last();
    $started = microtime(true);
    $stream = @fopen($url, 'rb', false, $context);
    $result['responseTimeMs'] = (int)round((microtime(true) - $started) * 1000);
    $headers = $stream ? (stream_get_meta_data($stream)['wrapper_data'] ?? []) : [];
    if ($stream) fclose($stream);
    foreach ((array)$headers as $header) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~i', (string)$header, $match)) $result['httpStatusCode'] = (int)$match[1];
    }
    $code = (int)($result['httpStatusCode'] ?? 0);
    $result['status'] = $code >= 100 && $code < 500 ? 'Online' : 'Offline';
    $lastError = error_get_last();
    $result['error'] = $code >= 400 ? 'HTTP ' . $code : ($stream ? '' : cleanText($lastError['message'] ?? 'Kết nối thất bại', 180));
    $host = (string)parse_url($url, PHP_URL_HOST);
    $result['dnsStatus'] = $host !== '' && gethostbyname($host) === $host ? 'Failed' : 'OK';
    if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
        $params = stream_context_get_params($context);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($certificate && function_exists('openssl_x509_parse')) {
            $parsed = openssl_x509_parse($certificate);
            $expiry = (int)($parsed['validTo_time_t'] ?? 0);
            if ($expiry) {
                $days = (int)floor(($expiry - time()) / 86400);
                $result['sslDaysLeft'] = $days;
                $result['sslStatus'] = $days < 0 ? 'Expired' : ($days <= 30 ? 'Expiring Soon' : 'OK');
            }
        } elseif (!$stream) {
            $result['sslStatus'] = 'Failed';
        }
    }
    return $result;
}

function checkSiteHealth(array $sites): array
{
    // InfinityFree exposes parts of cURL but disables curl_multi_exec() on
    // free hosting. Check every required function before using the multi API.
    if (function_exists('curl_multi_init') && function_exists('curl_multi_exec')) {
        return checkSitesWithCurl($sites);
    }
    if (function_exists('curl_init') && function_exists('curl_exec')) {
        return checkSitesWithSequentialCurl($sites);
    }
    $result = [];
    foreach (array_slice($sites, 0, 100) as $site) {
        $id = (string)($site['id'] ?? '');
        $url = normalizeUrl((string)($site['url'] ?? ''));
        if ($id !== '' && validHttpUrl($url)) $result[$id] = checkSiteWithStream($url);
    }
    return $result;
}
