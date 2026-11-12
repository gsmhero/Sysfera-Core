<?php
declare(strict_types=1);
namespace Sysfera;

/** The kernel: wires config, database, language, plugins and routes together and dispatches the request. */
final class App
{
    private static ?App $inst = null;
    private array $cfg = [];
    private ?Db $db = null;
    private ?Auth $auth = null;
    private ?Plugins $plugins = null;
    private ?Updater $updater = null;
    private Router $router;
    private array $menu = [];
    private ?array $settings = null;
    private array $repos = [];

    public static function get(): App { return self::$inst ??= new App(); }

    private function __construct() { $this->router = new Router(); }

    public static function configFile(): string { return SF_ROOT . '/storage/config.php'; }
    public static function installed(): bool { return is_file(self::configFile()) && is_file(SF_ROOT . '/storage/installed.lock'); }

    /** URL path of the folder the platform lives in ('' for a domain root, '/sysfera' for a sub-folder). */
    public static function basePath(): string
    {
        $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        return rtrim($d === '/' || $d === '.' ? '' : $d, '/');
    }

    public static function log(string $msg): void { @file_put_contents(SF_ROOT . '/storage/error.log', date('c') . ' ' . $msg . "\n", FILE_APPEND); }

    public function config(): array { return $this->cfg ??= []; }
    public function db(): Db { return $this->db ??= new Db($this->cfg['db']); }
    public function auth(): ?Auth { return $this->auth ??= ($this->db ? new Auth($this->db) : null); }
    public function router(): Router { return $this->router; }
    public function plugins(): Plugins { return $this->plugins ??= new Plugins($this->db(), $this); }
    public function updater(): Updater { return $this->updater ??= new Updater($this); }

    /* ----- shared records ----- */
    public function contacts(): Repo
    {
        return $this->repos['contacts'] ??= new Repo($this->db(), 'contacts', ['owner_id', 'type', 'name', 'email', 'phone', 'company', 'address', 'notes', 'source', 'ref', 'custom'], 'contact', ['custom']);
    }
    public function orders(): Documents
    {
        return $this->repos['orders'] ??= new Documents($this->db(), 'orders', ['owner_id', 'number', 'contact_id', 'status', 'currency', 'total', 'source', 'ref', 'meta'], 'order', 'order_lines', 'order_id', 'order');
    }
    public function invoices(): Documents
    {
        return $this->repos['invoices'] ??= new Documents($this->db(), 'invoices', ['owner_id', 'number', 'order_id', 'contact_id', 'status', 'currency', 'amount', 'issued_at', 'due_at', 'paid_at', 'source', 'meta'], 'invoice', 'invoice_lines', 'invoice_id', 'invoice');
    }

    /** Next document number, e.g. ORD-000012 (atomic). */
    public static function nextNumber(string $kind): string
    {
        $db = self::get()->db();
        $db->q('INSERT IGNORE INTO {p}settings (k, v) VALUES (?, ?)', ["counter.$kind", '0']);
        $db->q('UPDATE {p}settings SET v = LAST_INSERT_ID(CAST(v AS UNSIGNED) + 1) WHERE k = ?', ["counter.$kind"]);
        $n = (int)$db->val('SELECT LAST_INSERT_ID()');
        return strtoupper(['order' => 'ORD', 'invoice' => 'INV'][$kind] ?? substr($kind, 0, 3)) . '-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
    }

    /* ----- settings ----- */
    public function setting(string $k, string $default = ''): string
    {
        $this->settings ??= array_column($this->db()->rows('SELECT k, v FROM {p}settings'), 'v', 'k');
        return $this->settings[$k] ?? $default;
    }
    public function setSetting(string $k, string $v): void
    {
        $this->db()->q('INSERT INTO {p}settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
        $this->settings[$k] = $v;
    }

    /* ----- menu ----- */
    public function addMenu(array $item): void { $this->menu[] = $item; }
    /** Sidebar items visible to the current user, in order. Core items first, then plugins. */
    public function menu(): array
    {
        $items = array_filter(Hooks::filter('menu.items', $this->menu), fn($i) => empty($i['cap']) || ($this->auth && $this->auth->can($i['cap'])));
        usort($items, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
        return $items;
    }

    /* ----- request ----- */
    public function run(): void
    {
        $cfgFile = self::configFile();
        if (!self::installed()) { header('Location: ' . self::basePath() . '/install.php'); exit; }
        $this->cfg = require $cfgFile;
        $this->db(); $this->auth();
        set_exception_handler(function (\Throwable $e) {
            self::log((string)$e);
            http_response_code(500);
            $admin = false; try { $admin = $this->auth()->isAdmin(); } catch (\Throwable) {}
            $detail = (!empty($this->cfg['debug']) || $admin) ? get_class($e) . ': ' . $e->getMessage() . "\n" . str_replace(SF_ROOT, 'core', $e->getFile()) . ':' . $e->getLine() : 'Something went wrong. See storage/error.log';   // administrators see the cause, visitors do not
            echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:40px"><h1>Error</h1><pre style="white-space:pre-wrap;font:14px/1.5 ui-monospace,Consolas,monospace">' . e($detail) . '</pre>';
        });
        if (is_file(SF_ROOT . '/storage/maintenance.flag') && time() - (int)@file_get_contents(SF_ROOT . '/storage/maintenance.flag') < 600) {
            http_response_code(503); header('Retry-After: 60');
            I18n::setLang('en');
            echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:40px"><h1>Updating…</h1><p>The site is being updated and will be back in a minute.</p>'; exit;
        }
        // keep the database in step with the files (e.g. right after an FTP upload of a new version)
        if (self::setting_version_differs()) { (new Migrator($this->db()))->run(SF_ROOT . '/app/migrations'); $this->setSetting('core_version', SF_VERSION); }

        sf_session();
        $user = $this->auth()->user();
        I18n::setLang($user['lang'] ?? ($_COOKIE['sflang'] ?? $this->setting('default_lang', 'en')));
        date_default_timezone_set($this->setting('timezone', 'UTC') ?: 'UTC');
        (require SF_ROOT . '/app/routes.php')($this);
        $this->plugins()->boot();
        Demo::tick($this);
        Hooks::do('app.booted', $this);

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        if ($path === '/index.php') $path = '/';
        $m = $this->router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', rawurldecode($path));
        if (!$m) { http_response_code(404); View::page(t('err.404'), '<div class="card empty"><h2>404</h2><p>' . e(t('err.404_text')) . '</p><a class="btn" href="' . e(url('/')) . '">' . e(t('nav.dashboard')) . '</a></div>'); return; }
        [$handler, $params, $opt] = $m;
        if (($opt['auth'] ?? false) || !empty($opt['cap']) || !empty($opt['admin'])) {
            $this->auth()->require($opt['cap'] ?? null);
            if (!empty($opt['admin']) && !$this->auth()->isAdmin()) $this->auth()->require('__admin__');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($opt['csrf'] ?? true) && !csrf_ok()) { http_response_code(400); echo 'Bad request (form expired). Go back and try again.'; return; }
        $out = $handler($params);
        if (is_string($out)) echo $out;
    }

    private static function setting_version_differs(): bool
    {
        return self::get()->setting('core_version', '') !== SF_VERSION;
    }
}
