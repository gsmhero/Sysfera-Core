<?php
declare(strict_types=1);

use SysferaMsg\{Client, Ctx, HubError, HubServer, Sig, Text};

/**
 * Sysfera Messenger: chat with attachments between users of ALL Sysfera installations, through the hub on core.sysfera.ru.
 * An installation connects once (Settings → Connect), then each user gets a Sysfera ID  <id>@<domain>.
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Util', 'Hub', 'Client'] as $f) require_once __DIR__ . "/lib/$f.php";
    $db = $sf->db();
    $cfg = fn(string $k, string $d = '') => $sf->setting($k, $d);
    $host = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $demo = fn() => \Sysfera\Demo::on();

    $hubObj = null;
    $hub = function () use (&$hubObj, $db, $cfg): ?HubServer {
        if ($cfg('hub_mode', '0') !== '1') return null;
        return $hubObj ??= new HubServer($db, SF_ROOT . '/storage/messenger');
    };
    $clientObj = null;
    $client = function () use (&$clientObj, $cfg, $hub, $sf, $host): Client {
        return $clientObj ??= new Client($cfg, $hub, fn() => $sf->auth()->id(), $host);
    };
    $go = fn(string $p = '') => redirect('/p/messenger/' . ltrim($p, '/'));
    $json = function (array $d, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
    $err = fn(?string $k) => ($k && ($m = $sf->t('e_' . $k)) !== 'messenger.e_' . $k) ? $m : $sf->t('e_other', ['k' => (string)$k]);

    // other components (Activity) use the same connection
    \Sysfera\Hooks::on('messenger.client', fn($v) => $demo() ? null : $client());
    \Sysfera\Hooks::on('messenger.hub', fn($v) => $hub());
    \Sysfera\Hooks::on('demo.caps', fn(array $caps) => array_values(array_diff($caps, ['messenger.admin'])));

    $sf->menu('menu', '/', ['order' => 12, 'icon' => 'chat']);

    /** Tell the hub who this user is (name, e-mail for the opt-in directory) — at most every 10 minutes. Returns the hub's answer or null. */
    $profile = function (bool $force = false) use ($sf, $client): ?array {
        sf_session();
        $c = $client(); if (!$c->ready()) return null;
        if (!$force && isset($_SESSION['msg_me']) && time() - $_SESSION['msg_me']['t'] < 600) return $_SESSION['msg_me']['d'];
        $u = $sf->auth()->user() ?? [];
        $r = $c->call('POST', 'me', [], ['name' => (string)($u['name'] ?? ''), 'email' => (string)($u['email'] ?? '')]);
        if (!$r['ok']) return null;
        $_SESSION['msg_me'] = ['t' => time(), 'd' => $r['data']];
        return $r['data'];
    };

    $guard = function (callable $fn) use ($sf, $demo, $client, $profile): callable {
        return function (array $p = []) use ($fn, $sf, $demo, $client, $profile) {
            if ($demo()) { $sf->render('notice', ['msg' => 'demo'], $sf->t('title')); return; }
            if (!$client()->ready()) { $sf->render('notice', ['msg' => 'not_connected'], $sf->t('title')); return; }
            return $fn($p, $profile());
        };
    };

    /* ---------- pages ---------- */
    $sf->route('GET', '/', $guard(function ($p, $me) use ($sf, $client, $err) {
        $q = trim((string)($_GET['q'] ?? ''));
        $in = $client()->call('GET', 'inbox');
        $found = $q !== '' ? $client()->call('GET', 'search', ['q' => $q]) : null;
        if (!$in['ok']) flash($err($in['error']), 'bad');
        $sf->render('index', ['chats' => $in['data']['chats'] ?? [], 'me' => $me, 'sid' => $client()->me(), 'q' => $q, 'found' => $found && $found['ok'] ? $found['data']['users'] : null], $sf->t('title'));
    }));

    $sf->route('GET', '/chat', $guard(function ($p, $me) use ($sf, $client, $err, $go) {
        $with = strtolower(trim((string)($_GET['with'] ?? '')));
        $r = $client()->call('GET', 'thread', ['with' => $with]);
        if (!$r['ok']) { flash($err($r['error']), 'bad'); $go(); }
        $sf->render('chat', ['peer' => $r['data']['peer'], 'msgs' => $r['data']['messages'], 'blocked' => $r['data']['blocked'], 'sid' => $client()->me()], $r['data']['peer']['name']);
    }));

    $sf->route('GET', '/poll', $guard(function ($p) use ($client) {
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        $r = $client()->call('GET', 'thread', ['with' => strtolower(trim((string)($_GET['with'] ?? ''))), 'after' => (int)($_GET['after'] ?? 0)], null, null, 6);
        echo json_encode($r['ok'] ? ['messages' => $r['data']['messages']] : ['error' => $r['error']], JSON_UNESCAPED_UNICODE);
    }));

    $sf->route('POST', '/send', $guard(function ($p) use ($sf, $client, $err) {
        $with = strtolower(trim((string)($_POST['with'] ?? ''))); $text = trim((string)($_POST['text'] ?? '')); $fileId = '';
        $back = fn() => redirect('/p/messenger/chat?with=' . rawurlencode($with));
        $up = $_FILES['file'] ?? null;
        if ($up && ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) { flash($sf->t('e_file_big'), 'bad'); $back(); }
            if ($up['size'] > Text::MAX_FILE) { flash($sf->t('e_file_big'), 'bad'); $back(); }
            if (!Text::file((string)$up['name'])) { flash($sf->t('e_file_type'), 'bad'); $back(); }
            $r = $client()->call('POST', 'upload', ['name' => (string)$up['name']], null, (string)file_get_contents($up['tmp_name']), 30);
            if (!$r['ok']) { flash($err($r['error']), 'bad'); $back(); }
            $fileId = (string)$r['data']['file'];
        }
        if ($text === '' && $fileId === '') $back();
        $r = $client()->call('POST', 'send', [], ['to' => $with, 'text' => $text, 'file' => $fileId]);
        if (!$r['ok']) flash($err($r['error']), 'bad');
        $back();
    }));

    $sf->route('GET', '/file', $guard(function ($p) use ($client) {
        $r = $client()->call('GET', 'file', ['id' => (string)($_GET['id'] ?? '')], null, null, 30);
        if (!$r['ok']) { http_response_code(404); echo 'Not found'; return; }
        $d = $r['data'];
        $bytes = isset($d['_file']) ? (string)file_get_contents($d['_file']['path']) : (string)base64_decode((string)($d['b64'] ?? ''), true);
        $f = $d['_file'] ?? $d;
        $mime = in_array($f['mime'] ?? '', Text::FILES, true) ? $f['mime'] : 'application/octet-stream';
        $inline = !empty($_GET['inline']) && str_starts_with($mime, 'image/');
        header('Content-Type: ' . $mime); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', (string)$f['name']) . '"; filename*=UTF-8\'\'' . rawurlencode((string)$f['name']));
        header('Content-Length: ' . strlen($bytes)); header('Cache-Control: private, max-age=3600');
        echo $bytes;
    }));

    $sf->route('POST', '/block', $guard(function ($p) use ($sf, $client, $err) {
        $sid = strtolower(trim((string)($_POST['sid'] ?? '')));
        $r = $client()->call('POST', 'block', [], ['sid' => $sid, 'on' => !empty($_POST['on'])]);
        flash($r['ok'] ? $sf->t(!empty($_POST['on']) ? 'blocked_ok' : 'unblocked_ok') : $err($r['error']), $r['ok'] ? 'ok' : 'bad');
        redirect('/p/messenger/chat?with=' . rawurlencode($sid));
    }));

    $sf->route('POST', '/profile', $guard(function ($p) use ($sf, $client, $err, $go) {
        $u = $sf->auth()->user() ?? [];
        $r = $client()->call('POST', 'me', [], ['name' => (string)($u['name'] ?? ''), 'email' => (string)($u['email'] ?? ''), 'discoverable' => !empty($_POST['discoverable'])]);
        unset($_SESSION['msg_me']);
        flash($r['ok'] ? $sf->t('saved') : $err($r['error']), $r['ok'] ? 'ok' : 'bad');
        $go();
    }));

    /* ---------- connection settings (administrators) ---------- */
    $sf->route('*', '/settings', function () use ($sf, $client, $cfg, $demo, $err, $host, $hub, $go) {
        if ($demo()) { $sf->render('notice', ['msg' => 'demo'], $sf->t('title')); return; }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? '');
            if ($do === 'save') {
                $u = rtrim(trim((string)($_POST['hub_url'] ?? '')), '/');
                if ($u !== '' && !preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?(/[A-Za-z0-9._/-]*)?$~', $u)) { flash($sf->t('e_bad_hub'), 'bad'); $go('settings'); }
                $sf->setSetting('hub_url', $u !== '' ? $u : 'https://core.sysfera.ru');
                $sf->setSetting('hub_mode', !empty($_POST['hub_mode']) ? '1' : '0');
                flash($sf->t('saved'));
            } elseif ($do === 'connect') {
                $base = 'https://' . $host . \Sysfera\App::basePath();
                $ch = bin2hex(random_bytes(16));
                $sf->setSetting('challenge', $ch);
                $r = $client()->connect($base, $ch);
                $sf->setSetting('challenge', '');
                if ($r['ok']) { $sf->setSetting('hub_key', (string)$r['data']['key']); flash($sf->t('connected_ok')); }
                else flash($err($r['error']), 'bad');
            } elseif ($do === 'disconnect') { $sf->setSetting('hub_key', ''); flash($sf->t('disconnected_ok')); }
            $go('settings');
        }
        $status = null;
        if ($client()->ready()) { $r = $client()->call('GET', 'unread'); $status = $r['ok'] ? true : $err($r['error']); }
        $sf->render('settings', ['host' => $host, 'url' => $cfg('hub_url', 'https://core.sysfera.ru'), 'key' => $cfg('hub_key', '') !== '', 'hubMode' => $hub() !== null, 'status' => $status, 'https' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'], $sf->t('settings'));
    }, ['cap' => 'messenger.admin']);

    /* ---------- machine endpoints ---------- */
    // the hub calls this to check that the installation really owns its domain
    $sf->api('GET', 'messenger/verify', function () use ($cfg, $json) {
        $c = $cfg('challenge', '');
        $json($c !== '' ? ['challenge' => $c] : ['challenge' => null]);
    });

    // an installation asks to be connected (hub side)
    $sf->api('POST', 'hub/register', function () use ($hub, $json) {
        $h = $hub(); if (!$h) { $json(['error' => 'not_hub'], 404); return; }
        $in = json_decode((string)file_get_contents('php://input', false, null, 0, 4096), true);
        $base = rtrim((string)($in['base'] ?? ''), '/'); $ch = (string)($in['challenge'] ?? '');
        $domain = Text::domain($base);
        if (!$domain || strlen($ch) < 16) { $json(['error' => 'bad_domain'], 400); return; }
        if (time() - $h->lastEnroll($domain) < 60) { $json(['error' => 'rate'], 429); return; }
        $ip = gethostbyname($domain);   // never call private or internal addresses
        if ($ip === $domain || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { $json(['error' => 'bad_domain'], 400); return; }
        [$code, $body] = Client::http('GET', $base . '/api/messenger/verify', ['Accept: application/json'], '', 6, $domain . ':443:' . $ip);
        $got = json_decode($body, true);
        if ($code !== 200 || !is_array($got) || !is_string($got['challenge'] ?? null) || !hash_equals($ch, $got['challenge'])) { $json(['error' => 'verify_failed'], 403); return; }
        $json(['domain' => $domain, 'key' => $h->enroll($domain, $base)]);
    });

    // every other hub operation: GET/POST /api/hub/<op>, signed with the installation's key
    $serve = function (array $p) use ($hub, $json): void {
        $h = $hub(); if (!$h) { $json(['error' => 'not_hub'], 404); return; }
        $op = (string)($p['op'] ?? '');
        $domain = strtolower((string)($_SERVER['HTTP_X_SF_DOMAIN'] ?? '')); $uid = (string)($_SERVER['HTTP_X_SF_USER'] ?? '');
        $raw = (string)file_get_contents('php://input', false, null, 0, Text::MAX_FILE + 65536);
        if (strlen($raw) > Text::MAX_FILE + 65000) { $json(['error' => 'file_big'], 413); return; }
        $key = preg_match('/^[a-z0-9.-]{4,190}$/', $domain) ? $h->installKey($domain) : '';
        if (!Sig::verify($key, (int)($_SERVER['HTTP_X_SF_TIMESTAMP'] ?? 0), (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), (string)($_SERVER['REQUEST_URI'] ?? ''), $uid, $raw, (string)($_SERVER['HTTP_X_SF_SIGNATURE'] ?? ''))) { $json(['error' => 'unauthorized'], 401); return; }
        $q = $_GET; $in = [];
        if ($op !== 'upload' && $raw !== '') { $in = json_decode($raw, true); if (!is_array($in)) { $json(['error' => 'bad_json'], 400); return; } }
        try {
            $r = $h->dispatch($op, new Ctx($domain, $uid, array_map('strval', array_filter($q, 'is_scalar')), $in, $op === 'upload' ? $raw : ''));
            if (isset($r['_file'])) $r = ['name' => $r['_file']['name'], 'mime' => $r['_file']['mime'], 'size' => $r['_file']['size'], 'b64' => base64_encode((string)file_get_contents($r['_file']['path']))];
            $json($r);
        } catch (HubError $e) { $json(['error' => $e->key], $e->status); }
        catch (\Throwable $e) { \Sysfera\App::log('messenger hub ' . $op . ' ' . $e); $json(['error' => 'server_error'], 500); }
    };
    $sf->api('GET', 'hub/{op}', $serve);
    $sf->api('POST', 'hub/{op}', $serve);
};
