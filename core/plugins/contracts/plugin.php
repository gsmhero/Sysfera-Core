<?php
declare(strict_types=1);

use SysferaContracts\{Err, Ctx, Svc, DbStore, Notice, Bablito, Sync};

/**
 * Sysfera Contracts, ported from the standalone Bablito app. The rules live in lib/Svc.php (tested without a database);
 * this file wires them to the platform:
 *  - users, login, administrators: the Sysfera ones (an administrator may do what an author may and manages categories and profile fields);
 *  - pages /p/contracts/...; every action is a CSRF-protected POST that redirects back with a flash message;
 *  - files are kept in core/storage/contracts (not web-reachable) and served through /file/{id} after a permission check;
 *  - e-mails use the platform mailer (silent until SMTP is set up in Settings); the bell always works;
 *  - no secrets: nothing of Bablito's .env (database, SMTP, YooKassa, VK / OK keys) is used or stored here.
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Err', 'Ctx', 'Store', 'Svc', 'Notice', 'DbStore', 'Bablito', 'Sync'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db();
    $L = ['en' => require __DIR__ . '/lang/en.php', 'ru' => require __DIR__ . '/lang/ru.php'];
    $users = function (array $ids) use ($db): array {
        $out = []; foreach ($db->rows('SELECT id, name, email, lang FROM {p}users WHERE status = \'active\' AND id IN (' . implode(',', array_map('intval', $ids)) . ')') as $r) $out[(int)$r['id']] = $r; return $out;
    };
    $base = function (): string { $h = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? '')); return $h === '' ? '' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $h); };
    $mail = function (int $uid, string $kind, array $d, string $path) use ($sf, $users, $L, $base): void {
        $u = ($users([$uid]))[$uid] ?? null; if (!$u || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return;
        $l = $L[($u['lang'] ?? '') === 'ru' ? 'ru' : 'en']; $lines = Notice::lines($l, $kind, $d); $lines[] = ''; $lines[] = $l['mail_open'] . ': ' . Notice::link($base() . $sf->url(''), $path); $lines[] = ''; $lines[] = $l['mail_why'];
        $sf->mail($u['email'], Notice::headline($l, $kind, $d), implode("\n", $lines));
    };
    $svc = function () use ($db, $users, $mail, $sf): Svc {
        static $o = null; return $o ??= new Svc(new DbStore($db), fn() => time(), $users, $mail, ['stale_days' => max(1, (int)$sf->setting('stale_days', '30')), 'bablito_base' => trim((string)$sf->setting('bablito_url', 'https://www.bablito.ru'))]);
    };
    $me = function () use ($sf): Ctx { $u = $sf->auth()->user(); return new Ctx((int)($u['id'] ?? 0), false, (string)($u['name'] ?? '')); /* Bablito has its own main administrator: a Sysfera administrator has no special rights here */ };
    $dir = SF_ROOT . '/storage/contracts';

    /* ---- Bablito.ru: the shared signing key comes from the SYSFERA_BABLITO_KEY environment variable, else from the write-only setting; it is never shown, logged or put in a page ---- */
    $bUrl = fn(): string => trim((string)$sf->setting('bablito_url', 'https://www.bablito.ru'));
    $bKey = fn(): string => (string)(getenv('SYSFERA_BABLITO_KEY') ?: $sf->setting('bablito_key', ''));
    $bLive = fn(): bool => $sf->setting('bablito_live', '0') === '1';
    $bHttp = function (string $method, string $url, array $headers, string $body): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $r = curl_exec($ch); if ($r === false) { $m = curl_error($ch); curl_close($ch); throw new \RuntimeException($m); }
        $st = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch); return [$st, (string)$r];
    };
    $syncUsers = function (?array $ids) use ($db): array {
        $w = $ids === null ? '' : ' AND id IN (' . implode(',', array_map('intval', $ids ?: [0])) . ')'; $out = [];
        foreach ($db->rows("SELECT id, name, email, lang FROM {p}users WHERE status = 'active'$w ORDER BY id LIMIT 5000") as $r) $out[(int)$r['id']] = $r; return $out;
    };
    $bablito = fn(): Bablito => new Bablito($bUrl(), $bKey(), $bHttp, fn() => time());
    $sync = fn(): Sync => new Sync(new DbStore($db), $bablito(), fn() => time(), $syncUsers, function (int $id) use ($base, $sf): ?string { $b = $base(); return str_starts_with($b, 'https://') ? $b . $sf->url('/view/' . $id) : null; });
    $bConfigured = fn(): bool => Bablito::validBase($bUrl()) && strlen($bKey()) >= 32;

    /* dashboard widget: the three newest contracts and the statement of "My contracts" */
    $sf->on('dashboard.widgets', function (array $w) use ($sf, $svc, $me) {
        if (\Sysfera\Demo::on() || $me()->uid <= 0) return $w;
        try { $g = $svc()->glance($me(), 3); } catch (\Throwable $e) { return $w; }
        $w['contracts'] = \Sysfera\View::file($sf->dir . '/views/widget.php', ['sf' => $sf, 'g' => $g]);
        return $w;
    });
    $sf->menu('menu', '/', ['order' => 24, 'icon' => 'note', 'badge' => fn() => $sf->auth()->id() ? $svc()->unread($sf->auth()->id()) : 0]);

    /* the stale-contract sweep runs at most every 10 minutes, on page loads */
    $sweep = function () use ($sf, $svc, $bLive, $bConfigured, $sync): void {
        if ($bLive() && $bConfigured() && (int)$sf->setting('last_import', '0') < time() - 900) {
            $sf->setSetting('last_import', (string)time()); try { $sync()->contracts(false); $sync()->pushContracts(false); } catch (\Throwable $e) { \Sysfera\App::log('contracts import ' . $e->getMessage()); }
        }
        $last = (int)$sf->setting('last_sweep', '0'); if ($last > time() - 600) return;
        $sf->setSetting('last_sweep', (string)time()); try { $svc()->archiveStale(); } catch (\Throwable $e) { \Sysfera\App::log('contracts sweep ' . $e); }
    };

    $errText = function (Err $e) use ($sf): string { $t = $sf->t('err_' . $e->errCode); $t = $t === 'contracts.err_' . $e->errCode ? $e->getMessage() : $t; return in_array($e->errCode, ['field_required', 'field_invalid', 'step_numbers', 'bablito_refused', 'bablito_unreachable', 'bablito_bad_answer'], true) ? $t . ' (' . $e->getMessage() . ')' : $t; };
    $safeBack = function (string $p, string $default): string { return ($p !== '' && $p[0] !== '/' && !str_contains($p, '//') && !str_contains($p, '\\') && !str_contains($p, ':')) ? $p : $default; };
    /** Runs an action; on a refusal shows the message and goes to $back. */
    $act = function (callable $fn, string $back) use ($sf, $errText, $bLive, $bConfigured, $sync): void {
        sf_session(); try { $r = $fn(); if (is_string($r) && $r !== '') $back = $r;
            if ($bLive() && $bConfigured() && (int)$sf->setting('last_push', '0') < time() - 20) { $sf->setSetting('last_push', (string)time()); try { $sync()->pushContracts(false); } catch (\Throwable $e) { \Sysfera\App::log('contracts push ' . $e->getMessage()); } } /* a contract was created / published / changed: Bablito shows it too */ }
        catch (Err $e) { flash($errText($e), $e->http === 403 || $e->http === 404 ? 'bad' : 'bad'); }
        catch (\Throwable $e) { \Sysfera\App::log('contracts ' . $e); flash($sf->t('err_server'), 'bad'); }
        redirect($back === '@profile' ? '/profile' : '/p/contracts/' . ltrim($back, '/'));
    };
    $page = function (callable $fn) use ($sf, $sweep): callable {
        return function (array $p = []) use ($fn, $sf, $sweep) {
            sf_session(); $sweep();
            try { $fn($p); }
            catch (Err $e) { http_response_code($e->http); echo '<div class="card"><h3>' . e($sf->t($e->http === 404 ? 'not_found' : 'forbidden')) . '</h3><p><a class="btn" href="' . e($sf->url('/')) . '">' . e($sf->t('nav_list')) . '</a></p></div>'; }
        };
    };
    $render = function (string $view, array $vars, string $title, string $tab) use ($sf, $me, $svc, $L, $bLive, $bConfigured) {
        $c = $me(); $lang = ($sf->auth()->user()['lang'] ?? '') === 'ru' ? 'ru' : 'en';
        $sf->render($view, $vars + ['babOn' => $bLive() && $bConfigured(), 'platformAdmin' => $sf->auth()->isAdmin(), 'tab' => $tab, 'me' => $c, 'svc' => $svc(), 'unread' => $svc()->unread($c->uid), 'lang' => $lang, 'L' => $L[$lang]], $title);
    };

    /* ---------------------------------- uploads ---------------------------------- */
    $store = function (array $f) use ($dir): array {   // -> [stored name, original name] | error key
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return ['', 'file_fail'];
        if ((int)$f['size'] > 8_000_000) return ['', 'file_big'];
        $orig = Svc::safeName((string)$f['name']); if (!Svc::extOk($orig)) return ['', 'file_type'];
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        return @move_uploaded_file($f['tmp_name'], "$dir/$name") ? [$name, $orig] : ['', 'file_fail'];
    };
    $uploadMany = function (string $field, int $cid, string $kind) use ($store, $svc, $me, $sf, $dir): void {
        if (empty($_FILES[$field]['name'])) return; $names = (array)$_FILES[$field]['name'];
        foreach ($names as $i => $_) {
            $one = []; foreach (['name', 'tmp_name', 'size', 'error'] as $k) $one[$k] = is_array($_FILES[$field][$k]) ? $_FILES[$field][$k][$i] : $_FILES[$field][$k];
            if ($one['error'] === UPLOAD_ERR_NO_FILE) continue;
            [$stored, $orig] = $store($one); if ($stored === '') { flash($sf->t('err_' . $orig), 'bad'); continue; }
            try { $svc()->addFile($me(), $cid, $kind, $stored, $orig); } catch (Err $e) { @unlink("$dir/$stored"); flash($sf->t('err_' . $e->errCode), 'bad'); }
        }
    };

    /* ---------------------------------- pages ---------------------------------- */
    $sf->route('GET', '/', $page(function () use ($render, $svc, $sf) {
        $f = array_intersect_key($_GET, array_flip(['category', 'from', 'to', 'q', 'sort', 'page'])); $l = $svc()->listing($f);
        $render('list', ['list' => $l, 'f' => $f, 'cats' => $svc()->categories()], $sf->t('nav_list'), 'list');
    }));
    $sf->route('GET', '/remote/{id}', $page(function (array $p) use ($render, $svc, $sf, $me, $bLive, $bConfigured, $sync) {
        $k = $svc()->remote((int)$p['id']); $mine = null;
        if ($me()->uid && $bLive() && $bConfigured()) { try { $mine = $sync()->myOffer($me()->uid, (int)$p['id']); } catch (\Throwable $e) { $mine = null; } }
        $render('remote', ['k' => $k, 'mine' => $mine], $k['title'], 'list');
    }));
    /* An offer on a Bablito contract, made here: the user acts as their own Bablito account, Bablito notifies the author */
    $sf->route('POST', '/remote/{id}/offer', function (array $p) use ($sf, $me, $svc, $bLive, $bConfigured, $sync) {
        sf_session(); $id = (int)$p['id'];
        try {
            if (!$me()->uid) throw new Err('login_required', '', 401);
            if (!$bLive() || !$bConfigured()) throw new Err('bablito_not_live');
            $svc()->remote($id); $sync()->makeOffer($me()->uid, $id, $_POST);
            flash($sf->t('offer_sent'));
        } catch (Err $e) { flash($e->errCode === 'bablito_refused' || $e->errCode === 'bablito_unreachable' ? $sf->t('err_' . $e->errCode) . ' (' . $e->getMessage() . ')' : $sf->t('err_' . $e->errCode), 'bad'); }
        catch (\Throwable $e) { \Sysfera\App::log('contracts bablito offer ' . $e->getMessage()); flash($sf->t('err_server'), 'bad'); }
        redirect('/p/contracts/remote/' . $id);
    });
    $sf->route('GET', '/mine', $page(function () use ($render, $svc, $sf, $me) {
        $tab = in_array($_GET['tab'] ?? '', ['created', 'working', 'offers', 'archive', 'watch'], true) ? $_GET['tab'] : 'created';
        $render('mine', ['rows' => $svc()->mine($me(), $tab), 'sub' => $tab], $sf->t('nav_mine'), 'mine');
    }));
    $sf->route('GET', '/new', $page(function () use ($render, $svc, $sf, $me) {
        $render('edit', ['k' => null, 'steps' => [], 'locked' => false, 'files' => [], 'cats' => $svc()->categories(), 'myTypes' => $svc()->userTypes($me()->uid), 'form' => $_SESSION['ct_form'] ?? null], $sf->t('contract_new'), 'mine'); unset($_SESSION['ct_form']);
    }));
    $sf->route('GET', '/edit/{id}', $page(function (array $p) use ($render, $svc, $sf, $me) {
        $c = $me(); $k = $svc()->contract((int)$p['id']); if (!$k) throw new Err('not_found', '', 404);
        if (!$svc()->isOwner($c, $k) && !$c->admin) throw new Err('forbidden', '', 403);
        if (!in_array($k['status'], ['draft', 'published'], true)) { flash($sf->t('err_locked'), 'bad'); redirect('/p/contracts/view/' . $k['id']); }
        $locked = (bool)$svc()->offerCount((int)$k['id']);
        $render('edit', ['k' => $k, 'steps' => $svc()->steps((int)$k['id']), 'locked' => $locked, 'files' => $svc()->page($c, (int)$k['id'])['files'], 'cats' => $svc()->categories(), 'myTypes' => $svc()->userTypes((int)$k['user_id']), 'form' => $_SESSION['ct_form'] ?? null], $sf->t('contract_edit'), 'mine'); unset($_SESSION['ct_form']);
    }));
    $sf->route('POST', '/save', function () use ($act, $svc, $me, $sf, $uploadMany, $dir) {
        $id = (int)($_POST['id'] ?? 0); $in = $_POST; $c = $me();
        $act(function () use ($id, $in, $svc, $c, $sf, $uploadMany, $dir) {
            try { $k = $svc()->saveContract($c, $id, $in); }
            catch (Err $e) { $_SESSION['ct_form'] = $in; throw $e; }
            foreach ((array)($in['delete_file'] ?? []) as $fid) { $n = $svc()->removeFile($c, (int)$k['id'], (int)$fid); if ($n) @unlink("$dir/$n"); }
            $uploadMany('images', (int)$k['id'], 'image'); $uploadMany('files', (int)$k['id'], 'file');
            flash($sf->t('saved')); return 'view/' . $k['id'];
        }, $id ? 'edit/' . $id : 'new');
    });
    $sf->route('GET', '/view/{id}', $page(function (array $p) use ($render, $svc, $me, $sf) {
        $c = $me(); $id = (int)$p['id']; $d = $svc()->page($c, $id, (int)($_GET['thread'] ?? 0));
        if (!$d['working'] && !$d['isOwner'] && empty($_SESSION['ct_viewed'][$id])) { $svc()->addView($id); $_SESSION['ct_viewed'][$id] = 1; }
        $pay = (int)($_GET['pay'] ?? 0);
        $render('view', $d + ['draft' => $_SESSION['ct_offer'][$id] ?? null, 'payOpen' => $pay], $d['contract']['title'], 'list'); unset($_SESSION['ct_offer'][$id]);
    }));
    $sf->route('POST', '/c/{id}/{act}', function (array $p) use ($act, $svc, $me, $sf, $uploadMany) {
        $id = (int)$p['id']; $a = (string)$p['act']; $c = $me(); $back = 'view/' . $id;
        $act(function () use ($id, $a, $c, $svc, $sf, $uploadMany, $back) {
            switch ($a) {
                case 'offer':
                    try { $oid = $svc()->makeOffer($c, $id, $_POST, ($_POST['mode'] ?? '') === 'accept'); }
                    catch (Err $e) { if (!in_array($e->errCode, ['offer_exists', 'offer_not_allowed'], true)) $_SESSION['ct_offer'][$id] = $_POST; throw $e; }
                    flash($sf->t(($_POST['mode'] ?? '') === 'accept' ? 'flash_offer_asis' : 'flash_offer')); return 'offer/' . $oid;
                case 'comment':
                    $cm = $svc()->comment($c, $id, ($svc()->contract($id)['status'] ?? '') === 'published' ? 'public' : 'work', (string)($_POST['body'] ?? ''), (int)($_POST['thread'] ?? 0));
                    return $back . ($cm && ($svc()->contract($id)['status'] ?? '') === 'published' && (int)($_POST['thread'] ?? 0) ? '?thread=' . (int)$_POST['thread'] : '') . '#comments';
                case 'upload': $uploadMany('upload', $id, ($_POST['kind'] ?? '') === 'image' ? 'image' : 'file'); return $back;
                case 'pause': $svc()->pause($c, $id); flash($sf->t('saved')); return $back;
                case 'fire': $svc()->fire($c, $id); flash($sf->t('flash_fired')); return 'mine?tab=archive';
                case 'prepay': $svc()->prepay($c, $id); flash($sf->t('flash_prepaid')); return $back;
                case 'repeat': $n = $svc()->repeat($c, $id); flash($sf->t('flash_repeat')); return 'edit/' . $n;
                case 'delete': foreach ($svc()->delete($c, $id) as $n) @unlink(SF_ROOT . '/storage/contracts/' . $n); flash($sf->t('flash_deleted')); return 'mine';
                case 'watch': $svc()->watchToggle($c, $id); return $_POST['return'] ?? $back;
                default: throw new Err('not_found', '', 404);
            }
        }, $back);
    });
    $sf->route('GET', '/offer/{id}', $page(function (array $p) use ($render, $svc, $me, $sf) {
        $id = (int)$p['id']; $d = $svc()->offerPage($me(), $id);
        $render('offer', $d + ['draft' => $_SESSION['ct_offer']['o' . $id] ?? null, 'edit' => !empty($_GET['edit']), 'myTypes' => $svc()->userTypes($me()->uid)], $sf->t('offer'), 'list'); unset($_SESSION['ct_offer']['o' . $id]);
    }));
    $sf->route('POST', '/o/{id}/{act}', function (array $p) use ($act, $svc, $me, $sf) {
        $id = (int)$p['id']; $a = (string)$p['act']; $c = $me(); $back = 'offer/' . $id;
        $act(function () use ($id, $a, $c, $svc, $sf, $back) {
            $o = $svc()->offer($id);
            switch ($a) {
                case 'accept': $cid = $svc()->acceptOffer($c, $id); flash($sf->t('flash_accepted')); return 'view/' . $cid;
                case 'reject': $cid = $svc()->rejectOffer($c, $id); flash($sf->t('flash_rejected')); return 'view/' . $cid;
                case 'delete': $cid = $svc()->withdrawOffer($c, $id); flash($sf->t('flash_withdrawn')); return 'view/' . $cid;
                case 'edit':
                    try { $svc()->editOffer($c, $id, $_POST); } catch (Err $e) { $_SESSION['ct_offer']['o' . $id] = $_POST; flash($sf->t('err_' . $e->errCode), 'bad'); return $back . '?edit=1'; }
                    flash($sf->t('flash_offer_updated')); return $back;
                case 'comment': $svc()->comment($c, (int)$o['contract_id'], 'offer', (string)($_POST['body'] ?? ''), 0, $id); return $back . '#comments';
                default: throw new Err('not_found', '', 404);
            }
        }, $back);
    });
    $sf->route('POST', '/t/{id}/{act}', function (array $p) use ($act, $svc, $me, $sf) {
        $id = (int)$p['id']; $a = (string)$p['act']; $c = $me(); $t = $svc()->todo($id); $cid = (int)($t['contract_id'] ?? 0); $back = 'view/' . $cid . '#todo-' . $id;
        $act(function () use ($id, $a, $c, $svc, $sf, $cid, $back) {
            switch ($a) {
                case 'done': $svc()->done($c, $id); return $back;
                case 'accept': $r = $svc()->accept($c, $id); if ($r['fulfilled']) flash($sf->t('flash_fulfilled')); elseif ($r['invoice']) flash($sf->t('flash_invoiced'));
                    return $r['invoice'] && ($_POST['pay'] ?? '') === 'now' ? 'view/' . $cid . '?pay=' . $r['invoice'] : $back;
                case 'comment': $svc()->comment($c, $cid, 'todo', (string)($_POST['body'] ?? ''), 0, 0, $id); return $back;
                default: throw new Err('not_found', '', 404);
            }
        }, $back);
    });
    $sf->route('GET', '/invoices', $page(function () use ($render, $svc, $me, $sf) {
        $tab = ($_GET['tab'] ?? 'pay') === 'receive' ? 'receive' : 'pay'; $sort = ($_GET['sort'] ?? '') === 'contract' ? 'contract' : 'date'; $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc'; $q = trim((string)($_GET['q'] ?? ''));
        $render('invoices', ['list' => $svc()->invoices($me(), $tab, $q, $sort, $dir), 'sub' => $tab, 'sort' => $sort, 'dir' => $dir, 'q' => $q, 'counts' => $svc()->invoiceCounts($me())], $sf->t('nav_invoices'), 'invoices');
    }));
    $sf->route('GET', '/invoice/{id}', $page(function (array $p) use ($render, $svc, $me, $sf) {
        $d = $svc()->invoicePage($me(), (int)$p['id']); $render('invoice', $d, $sf->t('invoice') . ' ' . $d['invoice']['number'], 'invoices');
    }));
    $sf->route('POST', '/i/{id}/{act}', function (array $p) use ($act, $svc, $me, $sf) {
        $id = (int)$p['id']; $a = (string)$p['act']; $c = $me(); $back = $_POST['back'] ?? 'invoices';
        $act(function () use ($id, $a, $c, $svc, $sf, $back) {
            switch ($a) {
                case 'pay': $svc()->payInvoice($c, $id); flash($sf->t('flash_paid')); return $back;
                case 'confirm': $svc()->confirmInvoice($c, $id); flash($sf->t('flash_confirmed')); return $back;
                case 'number': $svc()->setNumber($c, $id, (string)($_POST['number'] ?? '')); flash($sf->t('saved')); return 'invoice/' . $id;
                default: throw new Err('not_found', '', 404);
            }
        }, 'invoice/' . $id);
    });
    $sf->route('GET', '/notices', $page(function () use ($render, $svc, $me, $sf, $L) {
        $list = $svc()->notices($me()); $svc()->markRead($me());
        $render('notices', ['list' => $list], $sf->t('nav_notices'), 'notices');
    }));
    $sf->route('POST', '/notices/clear', function () use ($act, $svc, $me) { $act(function () use ($svc, $me) { $svc()->clearNotices($me(), (int)($_POST['id'] ?? 0)); }, 'notices'); });

    $sf->route('GET', '/user/{id}', $page(function (array $p) use ($render, $svc, $me, $sf) {
        $id = (int)$p['id']; $d = $svc()->userPage($id); if (!$svc()->userExists($id)) throw new Err('not_found', '', 404);
        $c = $me(); $can = []; foreach ($svc()->reviewable($c, $id) as $k) $can[] = $k;
        $render('user', $d + ['uid' => $id, 'canReview' => $can], $d['user']['name'], 'list');
    }));
    $sf->route('POST', '/user/{id}/review', function (array $p) use ($act, $svc, $me, $sf) {
        $id = (int)$p['id']; $act(function () use ($id, $svc, $me, $sf) { $svc()->review($me(), (int)($_POST['contract_id'] ?? 0), $id, (int)($_POST['rating'] ?? 0), (string)($_POST['body'] ?? '')); flash($sf->t('flash_reviewed')); }, 'user/' . $id);
    });
    /* The profile lives on the platform page /profile (all three statuses, the same fields as on Bablito); this component adds its section there and saves it. */
    $sf->route('GET', '/profile', function () { redirect('/profile'); });
    $sf->on('profile.cards', function (array $cards, array $u) use ($svc, $me, $sf, $bLive, $bConfigured, $sync): array {
        $c = $me(); if ($c->uid <= 0) return $cards;
        $vars = ['profile' => $svc()->profile($c->uid), 'types' => $svc()->userTypes($c->uid), 'structure' => $svc()->structure(Svc::PARTY_TYPES), 'values' => $svc()->values($c->uid), 'me' => $c, 'sf' => $sf,
            'bablito' => ['on' => $bLive() && $bConfigured(), 'link' => $bConfigured() ? $sync()->link($c->uid) : null]];
        extract($vars); ob_start(); include __DIR__ . '/views/profile.php'; $cards[] = (string)ob_get_clean(); return $cards;
    });
    $sf->on('profile.save', function (string $do, array $u) use ($svc, $me, $sf, $errText, $bLive, $bConfigured, $sync): void {
        if ($do !== 'x_contracts') return;
        try {
            $svc()->saveProfile($me(), $_POST, (array)($_POST['pf'] ?? [])); flash($sf->t('saved'));
            if ($bLive() && $bConfigured()) { try { if ($sync()->link($me()->uid)) $sync()->profile($me()->uid, false); } catch (\Throwable $e) { \Sysfera\App::log('contracts bablito push ' . $e->getMessage()); flash($sf->t('bablito_push_failed')); } }
        } catch (Err $e) { flash($errText($e), 'bad'); }
    });
    /* Open Bablito already signed in (the account is registered first when needed): the way to make an offer on a contract that lives there */
    $sf->route('POST', '/bablito/go', function () use ($sf, $me, $svc, $bLive, $bConfigured, $sync) {
        sf_session();
        try {
            if (!$bLive() || !$bConfigured()) throw new Err('bablito_not_live');
            $to = (int)($_POST['to'] ?? 0); $path = '/contracts';
            if ($to > 0) { $svc()->remote($to); $path = '/contract/' . $to; }
            elseif (($_POST['page'] ?? '') === 'my') $path = '/my';
            $url = $sync()->goUrl($me()->uid, $path);
            header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); header('Location: ' . $url, true, 302); exit;
        } catch (Err $e) { flash($e->errCode === 'bablito_refused' || $e->errCode === 'bablito_unreachable' ? $sf->t('err_' . $e->errCode) . ' (' . $e->getMessage() . ')' : $sf->t('err_' . $e->errCode), 'bad'); }
        catch (\Throwable $e) { \Sysfera\App::log('contracts bablito go ' . $e->getMessage()); flash($sf->t('err_server'), 'bad'); }
        redirect('/p/contracts/');
    });

    /* a signed-in user creates / syncs their own Bablito account (live mode only) */
    $sf->route('POST', '/bablito/{act}', function (array $p) use ($act, $me, $sf, $bLive, $bConfigured, $sync) {
        $a = (string)$p['act'];
        $act(function () use ($a, $me, $sf, $bLive, $bConfigured, $sync) {
            if (!$bLive() || !$bConfigured()) throw new Err('bablito_not_live');
            $uid = $me()->uid;
            if ($a === 'create') { $r = $sync()->createAccount($uid, false); if (!in_array($r['status'], ['created', 'linked'], true)) throw new Err($r['status'] === 'exists' ? 'bablito_exists' : 'bablito_refused', $r['detail']); flash($sf->t($r['status'] === 'created' ? 'bablito_created' : 'bablito_linked')); }
            elseif ($a === 'sync') { $r = $sync()->profile($uid, false); flash(sprintf($sf->t('bablito_synced'), $r['pulled'], $r['pushed'])); }
            else throw new Err('not_found', '', 404);
        }, '@profile');
    });

    $sf->route('GET', '/file/{id}', function (array $p) use ($svc, $me, $sf, $dir) {
        try { $f = $svc()->file($me(), (int)$p['id']); } catch (Err $e) { http_response_code($e->http); echo e($sf->t($e->http === 404 ? 'not_found' : 'forbidden')); return; }
        $path = "$dir/" . basename($f['stored_name']); if (!is_file($path)) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $ext = strtolower(pathinfo($f['stored_name'], PATHINFO_EXTENSION)); $img = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        header('X-Content-Type-Options: nosniff'); header('Content-Type: ' . ($img[$ext] ?? 'application/octet-stream')); header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . (isset($img[$ext]) ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '_', $f['original_name']) . '"'); readfile($path);
    });

    /* ---------------------------------- administrator ---------------------------------- */
    $admin = fn(callable $fn) => $page(function (array $p = []) use ($fn, $sf) { if (!$sf->auth()->isAdmin()) throw new Err('forbidden', '', 403); $fn($p); });
    $sf->route('GET', '/admin', $admin(function () use ($render, $svc, $sf, $bUrl, $bKey, $bLive, $bConfigured, $db) {
        $last = json_decode((string)$sf->setting('bablito_last', ''), true);
        $render('admin', ['staleDays' => max(1, (int)$sf->setting('stale_days', '30')),
            'bab' => ['url' => $bUrl(), 'live' => $bLive(), 'key_set' => $bKey() !== '', 'key_env' => (bool)getenv('SYSFERA_BABLITO_KEY'), 'ok' => $bConfigured(), 'last' => is_array($last) ? $last : null, 'links' => (int)$db->val('SELECT COUNT(*) FROM {p}ct_links')]], $sf->t('nav_admin'), 'admin');
    }));
    $sf->route('GET', '/admin/profile-fields.csv', function () use ($svc, $sf) {
        if (!$sf->auth()->isAdmin()) { http_response_code(403); echo e($sf->t('forbidden')); return; }
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="contracts-profile-fields.csv"'); header('Cache-Control: no-store'); echo $svc()->fieldsCsv();
    });
    $sf->route('POST', '/admin/{what}', function (array $p) use ($act, $svc, $sf, $bablito, $sync, $bLive) {
        $what = (string)$p['what'];
        $act(function () use ($what, $svc, $sf, $bablito, $sync, $bLive) {
            if (!$sf->auth()->isAdmin()) throw new Err('forbidden', '', 403); $in = $_POST;
            switch ($what) {
                case 'settings': $sf->setSetting('stale_days', (string)max(1, min(365, (int)($in['stale_days'] ?? 30)))); break;
                case 'bablito':
                    $do = (string)($in['do'] ?? '');
                    if ($do === 'settings') {
                        $u = trim((string)($in['url'] ?? '')); if (!Bablito::validBase($u)) throw new Err('bablito_url');
                        $sf->setSetting('bablito_url', rtrim($u, '/')); $sf->setSetting('bablito_live', !empty($in['live']) ? '1' : '0');
                        if (trim((string)($in['key'] ?? '')) !== '') { if (strlen(trim($in['key'])) < 32) throw new Err('bablito_key_short'); $sf->setSetting('bablito_key', trim($in['key'])); }
                        elseif (!empty($in['clear_key'])) $sf->setSetting('bablito_key', '');
                    } elseif ($do === 'test') { $r = $bablito()->ping(); flash(sprintf($sf->t('bablito_ping_ok'), (int)($r['users'] ?? 0))); return; }
                    elseif ($do === 'preview' || $do === 'sync') {
                        if ($do === 'sync' && !$bLive()) throw new Err('bablito_not_live');
                        $rep = $sync()->all($do === 'preview'); $sf->setSetting('bablito_last', (string)json_encode($rep, JSON_UNESCAPED_UNICODE)); flash($sf->t($do === 'preview' ? 'bablito_previewed' : 'bablito_done')); return;
                    } elseif ($do === 'create') {
                        $r = $sync()->createAccount((int)($in['user_id'] ?? 0), !$bLive()); $k = 'bablito_acc_' . $r['status']; $m = $sf->t($k); flash($m === 'contracts.' . $k ? $r['status'] : $m); return;
                    } else throw new Err('not_found', '', 404);
                    break;
                default: throw new Err('not_found', '', 404);
            }
            flash($sf->t('saved'));
        }, 'admin');
    });
};
