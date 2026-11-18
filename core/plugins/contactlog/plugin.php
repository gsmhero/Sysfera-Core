<?php
declare(strict_types=1);

use SysferaContactlog\{Err, Svc, DbStore};

/**
 * Sysfera Contact Log, ported from the standalone "Agent's Call List" page. The rules live in lib/Svc.php (tested without a database);
 * this file wires them to the platform:
 *  - users and login: the Sysfera ones (the original's own register / login / "Admin" password screens are gone); every user sees only their own cards;
 *  - the page /p/contactlog/ is the original look; its script talks to a small session JSON API /p/contactlog/api/<op> (see openapi.yaml), CSRF-protected;
 *  - the e-mail report goes through the platform mailer (Settings > E-mail); nothing SMTP-related is stored or configured in this plugin, there is no fallback to PHP mail().
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Err', 'Store', 'Report', 'Svc', 'DbStore'] as $c) require_once __DIR__ . "/lib/$c.php";

    $L = ['en' => require __DIR__ . '/lang/en.php', 'ru' => require __DIR__ . '/lang/ru.php'];
    $lang = fn(): string => ($sf->auth()->user()['lang'] ?? '') === 'ru' ? 'ru' : 'en';
    $svc = function () use ($sf): Svc {
        static $o = null;
        return $o ??= new Svc(new DbStore($sf->db()), fn() => time(), fn($to, $subject, $text, $html, $from = null) => $sf->mail($to, $subject, $text, $html, $from));
    };
    $uid = fn(): int => $sf->auth()->id();
    $ownEmail = fn(): string => (string)($sf->auth()->user()['email'] ?? '');

    $sf->menu('menu', '/', ['order' => 26, 'icon' => 'chat']);

    /* dashboard widget: the next planned contacts and the latest ones (the user's own cards only) */
    $sf->on('dashboard.widgets', function (array $w) use ($sf, $svc, $uid) {
        if (\Sysfera\Demo::on() || $uid() <= 0) return $w;
        $w['contactlog'] = \Sysfera\View::file($sf->dir . '/views/widget.php', ['sf' => $sf, 'g' => $svc()->glance($uid(), date('Y-m-d')), 'colors' => Svc::COLORS]);
        return $w;
    });

    $sf->route('GET', '/', function () use ($sf, $svc, $uid, $lang, $L, $ownEmail) {
        $l = $lang();
        $sf->render('index', [
            'lang' => $l, 'L' => $L[$l], 'cards' => $svc()->cards($uid()), 'copyEmail' => $svc()->copyEmail($uid()), 'ownEmail' => $ownEmail(),
            'userName' => (string)($sf->auth()->user()['name'] ?? ''), 'isAdmin' => $sf->auth()->isAdmin(), 'api' => $sf->url('/api/'),
        ], $L[$l]['title']);
    });

    /** Session API for the page's script. Answers {"ok":true,...} or {"ok":false,"error":code,"message":text}. */
    /** The Messenger connection of this site (null when the Messenger component is off, not connected, or this is a demo). */
    $msgr = function (): ?object { $c = \Sysfera\Hooks::filter('messenger.client', null); return is_object($c) && $c->ready() ? $c : null; };
    /** Finds the contact on the hub: ['sid','name'] or null. Only people who are registered there (and allow search by e-mail) are found; a Sysfera ID always resolves. */
    $peer = function (int $me, int $id) use ($svc, $msgr): ?array {
        $c = $msgr(); if (!$c) throw new Err('msg_unavailable', '', 409);
        $q = $svc()->peerQuery($me, $id); if ($q === '') return null;
        $r = $c->call('GET', 'search', ['q' => $q]); if (!$r['ok']) throw new Err('msg_failed', (string)$r['error'], 502);
        $u = $r['data']['users'][0] ?? null; return $u ? ['sid' => (string)$u['sid'], 'name' => (string)$u['name']] : null;
    };
    $api = function (array $p) use ($sf, $svc, $uid, $lang, $L, $ownEmail, $msgr, $peer): void {
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        $l = $L[$lang()]; $op = (string)($p['op'] ?? ''); $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $send = function (int $status, array $d) { http_response_code($status); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION); };
        try {
            $me = $uid(); if ($me <= 0) throw new Err('forbidden', '', 403);
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$post) { if ($op !== 'cards') throw new Err('not_found', '', 404); $send(200, ['ok' => true, 'cards' => $svc()->cards($me)]); return; }
            switch ($op) {
                case 'save': $send(200, ['ok' => true, 'card' => $svc()->save($me, $id, $_POST)]); return;
                case 'delete': $svc()->delete($me, $id); $send(200, ['ok' => true]); return;
                case 'msgpeer': $x = $peer($me, $id); if (!$x) throw new Err('msg_notfound', '', 404); $send(200, ['ok' => true] + $x); return;
                case 'msgsend':
                    $x = $peer($me, $id); if (!$x) throw new Err('msg_notfound', '', 404);
                    $text = trim((string)($_POST['text'] ?? '')); if ($text === '' || mb_strlen($text) > 2000) throw new Err('mail_empty');
                    $r = $msgr()->call('POST', 'send', [], ['to' => $x['sid'], 'text' => $text, 'file' => '']);
                    if (!$r['ok']) throw new Err('msg_failed', (string)$r['error'], 502);
                    $send(200, ['ok' => true, 'logs' => $svc()->addLog($me, $id, 'messenger')]); return;
                case 'mail':
                    $u = $sf->auth()->user() ?? [];
                    $send(200, ['ok' => true, 'logs' => $svc()->sendMail($me, $id, (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? ''), (string)($u['email'] ?? ''), (string)($u['name'] ?? ''))]); return;
                case 'log': $send(200, ['ok' => true, 'logs' => $svc()->addLog($me, $id, (string)($_POST['action'] ?? ''))]); return;
                case 'clearlog': $svc()->clearLog($me, $id); $send(200, ['ok' => true]); return;
                case 'report': $r = $svc()->sendReport($me, $id, $ownEmail(), $l, date_default_timezone_get()); $send(200, ['ok' => true] + $r); return;
                case 'profile': $send(200, ['ok' => true, 'copy_email' => $svc()->saveCopyEmail($me, (string)($_POST['copy_email'] ?? ''))]); return;
                default: throw new Err('not_found', '', 404);
            }
        } catch (Err $e) {
            if ($e->errCode === 'mail_failed') \Sysfera\App::log('contactlog mail: ' . $e->getMessage());
            $m = ($l['err_' . $e->errCode] ?? $l['err_server']) . ($e->errCode === 'msg_failed' && $e->getMessage() !== '' ? ' (' . $e->getMessage() . ')' : ''); $send($e->http, ['ok' => false, 'error' => $e->errCode, 'message' => $m]);
        } catch (\Throwable $e) {
            \Sysfera\App::log('contactlog ' . $e); $send(500, ['ok' => false, 'error' => 'server', 'message' => $l['err_server']]);
        }
    };
    $sf->route('GET', '/api/{op}', $api);
    $sf->route('POST', '/api/{op}', $api);
};
