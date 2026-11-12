<?php
declare(strict_types=1);

/* Sysfera core bootstrap: constants, autoload, helper functions. No database is touched here. */

define('SF_ROOT', dirname(__DIR__));
define('SF_VERSION', trim((string)@file_get_contents(SF_ROOT . '/VERSION')) ?: '0.0.0');
const SF_MIN_PHP = '8.1.0';

spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'Sysfera\\')) {
        $f = SF_ROOT . '/app/' . str_replace('\\', '/', substr($c, 8)) . '.php';
        if (is_file($f)) require $f;
    }
});

/* ---------- small helpers used everywhere (core, views, plugins) ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Translate a key. Plugins use "slug.key". Falls back to English, then to the key itself. */
function t(string $key, array $vars = []): string { return \Sysfera\I18n::t($key, $vars); }

/** Absolute path inside the site (handles installs in a sub-folder). */
function url(string $path = '/'): string { return \Sysfera\App::basePath() . '/' . ltrim($path, '/'); }

function sf_session(): void {
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_NONE) return;
    session_name('sfsid');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']), 'path' => \Sysfera\App::basePath() . '/']);
    session_start();
}

function csrf_token(): string { sf_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="_t" value="' . csrf_token() . '">'; }
function csrf_ok(): bool { sf_session(); return isset($_POST['_t'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['_t']); }

function flash(string $msg, string $type = 'ok'): void { sf_session(); $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { sf_session(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function redirect(string $path): never {
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}

function human_size(int $b): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) { if ($b < 1024 || $i === 3) return ($i ? number_format($b, 1) : $b) . ' ' . $u; $b /= 1024; }
    return '';
}

function ago(?string $ts): string {
    if (!$ts) return '—';
    $d = time() - strtotime($ts);
    return $d < 60 ? t('time.now') : ($d < 3600 ? t('time.min', ['n' => (int)($d / 60)]) : ($d < 86400 ? t('time.hour', ['n' => (int)($d / 3600)]) : t('time.day', ['n' => (int)($d / 86400)])));
}
