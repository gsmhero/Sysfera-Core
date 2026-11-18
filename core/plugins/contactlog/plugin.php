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
        return $o ??= new Svc(new DbStore($sf->db()), fn() => time(), fn($to, $subject, $text, $html) => $sf->mail($to, $subject, $text, $html));
    };
    $uid = fn(): int => $sf->auth()->id();
    $ownEmail = fn(): string => (string)($sf->auth()->user()['email'] ?? '');

    $sf->menu('menu', '/', ['order' => 26, 'icon' => 'chat']);

    $sf->route('GET', '/', function () use ($sf, $svc, $uid, $lang, $L, $ownEmail) {
        $l = $lang();
        $sf->render('index', [
            'lang' => $l, 'L' => $L[$l], 'cards' => $svc()->cards($uid()), 'copyEmail' => $svc()->copyEmail($uid()), 'ownEmail' => $ownEmail(),
            'userName' => (string)($sf->auth()->user()['name'] ?? ''), 'isAdmin' => $sf->auth()->isAdmin(), 'api' => $sf->url('/api/'),
        ], $L[$l]['title']);
    });

    /** Session API for the page's script. Answers {"ok":true,...} or {"ok":false,"error":code,"message":text}. */
    $api = function (array $p) use ($sf, $svc, $uid, $lang, $L, $ownEmail): void {
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
                case 'log': $send(200, ['ok' => true, 'logs' => $svc()->addLog($me, $id, (string)($_POST['action'] ?? ''))]); return;
                case 'clearlog': $svc()->clearLog($me, $id); $send(200, ['ok' => true]); return;
                case 'report': $r = $svc()->sendReport($me, $id, $ownEmail(), $l, date_default_timezone_get()); $send(200, ['ok' => true] + $r); return;
                case 'profile': $send(200, ['ok' => true, 'copy_email' => $svc()->saveCopyEmail($me, (string)($_POST['copy_email'] ?? ''))]); return;
                default: throw new Err('not_found', '', 404);
            }
        } catch (Err $e) {
            if ($e->errCode === 'mail_failed') \Sysfera\App::log('contactlog mail: ' . $e->getMessage());
            $m = $l['err_' . $e->errCode] ?? $l['err_server']; $send($e->http, ['ok' => false, 'error' => $e->errCode, 'message' => $m]);
        } catch (\Throwable $e) {
            \Sysfera\App::log('contactlog ' . $e); $send(500, ['ok' => false, 'error' => 'server', 'message' => $l['err_server']]);
        }
    };
    $sf->route('GET', '/api/{op}', $api);
    $sf->route('POST', '/api/{op}', $api);
};
