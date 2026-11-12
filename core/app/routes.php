<?php
declare(strict_types=1);

use Sysfera\{App, Demo, Hooks, I18n, Plugins, View};

/** Core pages. Plugins add their own under /p/<slug>/... */
return function (App $app): void {
    $r = $app->router();
    $auth = $app->auth();
    $db = $app->db();

    $app->addMenu(['label' => 'nav.dashboard', 'path' => '/', 'order' => 0, 'icon' => 'home']);
    $app->addMenu(['label' => 'nav.plugins', 'path' => '/plugins', 'cap' => '__admin__', 'order' => 90, 'icon' => 'puzzle']);
    $app->addMenu(['label' => 'nav.users', 'path' => '/users', 'cap' => '__admin__', 'order' => 91, 'icon' => 'users']);
    $app->addMenu(['label' => 'nav.settings', 'path' => '/settings', 'cap' => '__admin__', 'order' => 92, 'icon' => 'cog']);
    $app->addMenu(['label' => 'nav.updates', 'path' => '/updates', 'cap' => '__admin__', 'order' => 93, 'icon' => 'refresh']);

    /* ---------- language switch ---------- */
    $r->add('GET', '/lang/{code}', function ($p) use ($auth, $db) {
        $code = $p['code'];
        if (isset(I18n::languages()[$code])) {
            setcookie('sflang', $code, ['expires' => time() + 86400 * 365, 'path' => App::basePath() . '/', 'samesite' => 'Lax']);
            if ($auth->id()) $db->q('UPDATE {p}users SET lang = ? WHERE id = ?', [$code, $auth->id()]);
        }
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        header('Location: ' . ($back !== '' ? $back : url('/'))); exit;
    });

    /* ---------- auth ---------- */
    $r->add('*', '/login', function () use ($auth) {
        if ($auth->user()) redirect('/');
        $err = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $err = $auth->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            if (!$err) { $n = $_SESSION['next'] ?? '/'; unset($_SESSION['next']); redirect(str_starts_with($n, '/') && !str_starts_with($n, '//') ? $n : '/'); }
        }
        View::page(t('auth.login'), View::file(SF_ROOT . '/views/login.php', ['err' => $err]), ['bare' => true]);
    });
    $r->add('POST', '/logout', function () use ($auth) { $auth->logout(); redirect('/login'); });

    $r->add('*', '/register', function () use ($app, $auth) {
        if ($auth->user()) redirect('/');
        $open = $app->setting('registration', '0') === '1' || Demo::on();
        $err = null;
        if ($open && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $res = $auth->register((string)($_POST['email'] ?? ''), (string)($_POST['name'] ?? ''), (string)($_POST['password'] ?? ''), 'user', I18n::lang());
            if (is_int($res)) {
                $caps = Demo::on() ? Demo::caps($app) : (json_decode($app->setting('default_caps', '[]'), true) ?: []);
                $app->db()->q('UPDATE {p}users SET caps = ? WHERE id = ?', [json_encode(array_values($caps)), $res]);
                $auth->start($res); flash(t('auth.welcome')); redirect('/');
            }
            $err = $res;
        }
        View::page(t('auth.register'), View::file(SF_ROOT . '/views/register.php', ['err' => $err, 'open' => $open]), ['bare' => true]);
    });

    /* ---------- dashboard & profile ---------- */
    $r->add('GET', '/', function () use ($app, $auth) {
        $widgets = Hooks::filter('dashboard.widgets', []);
        View::render('dashboard', [
            'counts' => ['contacts' => $app->contacts()->count(), 'orders' => $app->orders()->count(), 'invoices' => $app->invoices()->count()],
            'plugins' => array_filter($app->plugins()->available(), fn($m) => $app->plugins()->isEnabled($m['slug'])),
            'widgets' => $widgets, 'isAdmin' => $auth->isAdmin(),
        ], t('nav.dashboard'));
    }, ['auth' => true]);

    $r->add('*', '/profile', function () use ($auth, $db) {
        $u = $auth->user();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = $_POST['do'] ?? '';
            if ($do === 'password') {
                if (!password_verify((string)($_POST['old'] ?? ''), $u['password_hash'])) flash(t('profile.wrong_password'), 'bad');
                elseif (mb_strlen((string)($_POST['new'] ?? '')) < 8) flash(t('auth.short_password'), 'bad');
                else { $db->q('UPDATE {p}users SET password_hash = ? WHERE id = ?', [password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $u['id']]); flash(t('profile.saved')); }
            } else {
                $name = trim((string)($_POST['name'] ?? '')); $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $lang = isset(I18n::languages()[$_POST['lang'] ?? '']) ? $_POST['lang'] : null;
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash(t('auth.bad_email'), 'bad');
                elseif ($db->val('SELECT 1 FROM {p}users WHERE email = ? AND id <> ?', [$email, $u['id']])) flash(t('auth.email_taken'), 'bad');
                else { $db->q('UPDATE {p}users SET name = ?, email = ?, lang = ? WHERE id = ?', [$name, $email, $lang, $u['id']]); flash(t('profile.saved')); }
            }
            redirect('/profile');
        }
        View::render('profile', ['u' => $u], t('nav.profile'));
    }, ['auth' => true]);

    /* ---------- components (plugins) ---------- */
    $r->add('*', '/plugins', function () use ($app) {
        $pl = $app->plugins();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? ''); $slug = (string)($_POST['slug'] ?? '');
            try {
                if ($do === 'install') { $pl->install($slug); flash(t('plugins.installed_ok')); }
                elseif ($do === 'disable') { $pl->disable($slug); flash(t('plugins.disabled_ok')); }
                elseif ($do === 'uninstall') { $pl->uninstall($slug, !empty($_POST['files'])); flash(t('plugins.uninstalled_ok')); }
                elseif ($do === 'upload') {
                    if (empty($_FILES['zip']['tmp_name']) || ($_FILES['zip']['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException(t('plugins.bad_zip'));
                    $got = $pl->extractZip($_FILES['zip']['tmp_name']); flash(t('plugins.uploaded_ok', ['name' => $got]));
                } elseif ($do === 'download') {
                    $m = $app->updater()->manifest(true)['plugins'][$slug] ?? throw new RuntimeException(t('upd.none'));
                    $zip = $app->updater()->download($m);
                    try { $got = $pl->extractZip($zip); } finally { @unlink($zip); }
                    flash(t('plugins.uploaded_ok', ['name' => $got]));
                }
            } catch (Throwable $e) { flash($e->getMessage(), 'bad'); }
            redirect('/plugins');
        }
        $manifest = $app->updater()->manifest();
        View::render('plugins', ['available' => $pl->available(), 'installed' => $pl->installed(), 'remote' => (array)($manifest['plugins'] ?? []), 'updates' => $app->updater()->pluginUpdates($manifest), 'problems' => fn($m) => $pl->problems($m)], t('nav.plugins'));
    }, ['admin' => true]);

    /* ---------- users ---------- */
    $r->add('*', '/users', function () use ($app, $auth, $db) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $id = (int)($_POST['id'] ?? 0); $do = (string)($_POST['do'] ?? '');
            if ($do === 'create') {
                $res = $auth->register((string)($_POST['email'] ?? ''), (string)($_POST['name'] ?? ''), (string)($_POST['password'] ?? ''), in_array($_POST['role'] ?? '', ['admin', 'user'], true) ? $_POST['role'] : 'user');
                is_int($res) ? flash(t('users.created')) : flash(t($res), 'bad');
            } elseif ($id && $id !== $auth->id()) {
                if ($do === 'save') {
                    $caps = array_values(array_intersect((array)($_POST['caps'] ?? []), array_keys($app->plugins()->capabilities())));
                    $db->q('UPDATE {p}users SET role = ?, status = ?, caps = ? WHERE id = ?', [in_array($_POST['role'] ?? '', ['admin', 'user'], true) ? $_POST['role'] : 'user', ($_POST['status'] ?? '') === 'blocked' ? 'blocked' : 'active', json_encode($caps), $id]);
                    flash(t('users.saved'));
                } elseif ($do === 'delete') { $db->q('DELETE FROM {p}users WHERE id = ?', [$id]); flash(t('users.deleted')); }
            } else flash(t('users.not_self'), 'warn');
            redirect('/users');
        }
        View::render('users', ['users' => $db->rows('SELECT * FROM {p}users ORDER BY id'), 'caps' => $app->plugins()->capabilities(), 'me' => $auth->id()], t('nav.users'));
    }, ['admin' => true]);

    /* ---------- settings ---------- */
    $r->add('*', '/settings', function () use ($app) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (($_POST['do'] ?? '') === 'demo_reset') {
                if (!Demo::on() || ($_POST['confirm'] ?? '') !== 'RESET') { flash(t('settings.demo_confirm_bad'), 'bad'); redirect('/settings'); }
                Demo::reset($app); flash(t('settings.demo_done')); redirect('/settings');
            }
            if (in_array($_POST['do'] ?? '', ['mail_save', 'mail_test'], true)) {
                $set = fn(string $k, int $max = 190) => $app->setSetting($k, mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max));
                $set('smtp_host'); $set('smtp_user'); $set('smtp_from'); $set('smtp_from_name', 80); $set('notify_email', 300);
                $app->setSetting('smtp_port', (string)(max(1, min(65535, (int)($_POST['smtp_port'] ?? 587)))));
                $app->setSetting('smtp_secure', in_array($_POST['smtp_secure'] ?? '', ['auto', 'ssl', 'tls', 'none'], true) ? $_POST['smtp_secure'] : 'auto');
                if (trim((string)($_POST['smtp_pass'] ?? '')) !== '') $app->setSetting('smtp_pass', (string)$_POST['smtp_pass']);
                if (($_POST['do'] ?? '') === 'mail_test') {
                    $to = (string)($app->auth()->user()['email'] ?? '');
                    $err = \Sysfera\Mailer::send($to, t('mail.test_subject'), t('mail.test_text', ['site' => $app->setting('site_name', 'Sysfera')]));
                    $err === null ? flash(t('mail.test_ok', ['to' => $to])) : flash(t('mail.test_fail', ['e' => $err]), 'bad');
                } else flash(t('settings.saved'));
                redirect('/settings');
            }
            $app->setSetting('demo_mode', !empty($_POST['demo_mode']) ? '1' : '0');
            if (in_array((int)($_POST['demo_hours'] ?? 0), Demo::HOURS, true)) $app->setSetting('demo_hours', (string)(int)$_POST['demo_hours']);
            if (!empty($_POST['demo_mode']) && $app->setting('demo_last_reset', '0') === '0') $app->setSetting('demo_last_reset', (string)time());
            $name = trim((string)($_POST['site_name'] ?? ''));
            $app->setSetting('site_name', $name !== '' ? mb_substr($name, 0, 80) : 'Sysfera');
            if (isset(I18n::languages()[$_POST['default_lang'] ?? ''])) $app->setSetting('default_lang', $_POST['default_lang']);
            $tz = (string)($_POST['timezone'] ?? 'UTC');
            $app->setSetting('timezone', in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC');
            $app->setSetting('registration', !empty($_POST['registration']) ? '1' : '0');
            $app->setSetting('default_caps', json_encode(array_values(array_intersect((array)($_POST['default_caps'] ?? []), array_keys($app->plugins()->capabilities())))));
            $url = trim((string)($_POST['update_url'] ?? ''));
            $app->setSetting('update_url', preg_match('~^https?://~', $url) ? $url : '');
            flash(t('settings.saved')); redirect('/settings');
        }
        View::render('settings', ['caps' => $app->plugins()->capabilities(), 'langs' => I18n::languages()], t('nav.settings'));
    }, ['admin' => true]);

    /* ---------- updates ---------- */
    $r->add('*', '/updates', function () use ($app) {
        $up = $app->updater();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? '');
            try {
                if ($do === 'check') { $up->manifest(true) === null ? flash(t('upd.unreachable'), 'warn') : flash(t('upd.checked')); }
                elseif ($do === 'core') { flash(t('upd.done', ['v' => $up->applyCore()])); }
                elseif ($do === 'plugin') { flash(t('upd.done', ['v' => $up->applyPlugin((string)($_POST['slug'] ?? ''))])); }
            } catch (Throwable $e) { flash($e->getMessage(), 'bad'); }
            redirect('/updates');
        }
        $m = $up->manifest();
        View::render('updates', ['m' => $m, 'core' => $up->coreUpdate($m), 'plugins' => $up->pluginUpdates($m), 'backups' => $up->backups(), 'checked' => (int)$app->setting('update_checked', '0'), 'zip' => class_exists(ZipArchive::class)], t('nav.updates'));
    }, ['admin' => true]);

    /* ---------- core static files (core/assets/*), served through PHP so the web root stays tiny and updates replace them ---------- */
    $r->add('GET', '/assets/{file}', function ($p) {
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png'];
        $f = SF_ROOT . '/assets/' . basename($p['file']);
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!isset($types[$ext]) || !is_file($f)) { http_response_code(404); return; }
        header('Content-Type: ' . $types[$ext]); header('Cache-Control: public, max-age=86400'); readfile($f);
    });

    /* ---------- plugin static files (plugins/<slug>/assets/...) ---------- */
    $r->add('GET', '/assets/p/{slug}/{file*}', function ($p) use ($app) {
        if (!$app->plugins()->isEnabled($p['slug']) || str_contains($p['file'], '..')) { http_response_code(404); return; }
        $f = Plugins::dir() . '/' . $p['slug'] . '/assets/' . $p['file'];
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'woff2' => 'font/woff2', 'json' => 'application/json'];
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!isset($types[$ext]) || !is_file($f)) { http_response_code(404); return; }
        header('Content-Type: ' . $types[$ext]); header('Cache-Control: public, max-age=3600'); readfile($f);
    });
};
