<?php
declare(strict_types=1);

const APP_NAME = 'Web Hub';
const DATA_FILE = __DIR__ . '/data/sites.json';
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function ensureStorage(): void {
    if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE), 0755, true);
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (!file_exists(DATA_FILE)) file_put_contents(DATA_FILE, '[]', LOCK_EX);
}
function startSession(): void {
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
function jsonResponse(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function readSites(): array {
    ensureStorage();
    $fp = fopen(DATA_FILE, 'rb');
    if (!$fp || !flock($fp, LOCK_SH)) return [];
    $data = json_decode(stream_get_contents($fp) ?: '[]', true);
    flock($fp, LOCK_UN);
    fclose($fp);
    return is_array($data) && array_is_list($data) ? $data : [];
}
function updateSites(callable $change): mixed {
    ensureStorage();
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) throw new RuntimeException('Không thể mở dữ liệu.');
    if (!flock($fp, LOCK_EX)) { fclose($fp); throw new RuntimeException('Không thể khóa dữ liệu.'); }
    try {
        rewind($fp);
        $sites = json_decode(stream_get_contents($fp) ?: '[]', true);
        if (!is_array($sites) || !array_is_list($sites)) throw new RuntimeException('Dữ liệu website không hợp lệ.');
        $result = $change($sites);
        $json = json_encode(array_values($sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        rewind($fp);
        if (!ftruncate($fp, 0) || fwrite($fp, $json) !== strlen($json) || !fflush($fp)) throw new RuntimeException('Không thể ghi dữ liệu.');
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
function cleanText(mixed $value, int $max = 255): string {
    $value = trim(is_scalar($value) ? (string)$value : '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
function normalizeUrl(string $url): string {
    $url = trim($url);
    return $url !== '' && !preg_match('~^https?://~i', $url) ? 'https://' . $url : $url;
}
function validHttpUrl(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    return isset($parts['host'], $parts['scheme'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        && !isset($parts['user']) && !isset($parts['pass']);
}
function uploadedImagePath(string $path): bool {
    if ($path === '') return true;
    if (!preg_match('~^uploads/[a-zA-Z0-9-]+\.(?:jpg|png|webp|gif)$~', $path)) return false;
    return is_file(__DIR__ . '/' . $path);
}
function deleteUploadedImage(?string $path): void {
    if (!$path || !preg_match('~^uploads/[a-zA-Z0-9-]+\.(?:jpg|png|webp|gif)$~', $path)) return;
    $real = realpath(__DIR__ . '/' . $path);
    $root = realpath(UPLOAD_DIR);
    if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) @unlink($real);
}
function generateId(): string { return bin2hex(random_bytes(8)); }
