<?php
declare(strict_types=1);

use Sysfera\{App, Hooks, I18n, TaskTypes};
use SysferaMissions\Mission;

/**
 * Sysfera Missions — chains of tasks with three possible results each (ported from Tactify Mission Manager).
 * Everyone works with their own missions. A mission can be shared to the library (everybody) or with chosen teams (Teams component).
 * Task types come from the shared registry (Core → Task types), the same list CRM uses.
 */
return function (\Sysfera\Plugin $sf): void {
    require_once __DIR__ . '/lib/Mission.php';
    $db = $sf->db();
    $uid = fn(): int => $sf->auth()->id();
    $isAdmin = fn(): bool => $sf->auth()->isAdmin();
    $path = fn(string $p = ''): string => '/p/missions/' . ltrim($p, '/');
    $lang = fn(): string => I18n::lang();
    $cats = fn(): array => $db->rows('SELECT * FROM {p}mis_categories ORDER BY sort_order, name');
    $cron = function () use ($sf): string { $t = $sf->setting('cron_token', ''); if ($t === '') { $t = bin2hex(random_bytes(16)); $sf->setSetting('cron_token', $t); } return $t; };
    $store = SF_ROOT . '/storage/missions';
    $types = fn(): array => TaskTypes::all($db, null, true);              // every type, also the switched-off ones (old tasks keep their type)
    $offered = fn(): array => TaskTypes::all($db, 'missions');            // what the "Type" list offers
    $on = function (string $slug): bool { try { return App::get()->plugins()->isEnabled($slug); } catch (\Throwable) { return false; } };
    $teamsOn = fn(): bool => $on('teams') && $sf->can('teams.use');
    $crmOn = fn(): bool => $on('crm') && $sf->can('crm.use');
    /** run $fn with the interface language switched to $code (a mission can notify in its own language) */
    $inLang = function (string $code, callable $fn) { $old = I18n::lang(); if ($code !== '' && $code !== $old) I18n::setLang($code); try { return $fn(); } finally { I18n::setLang($old); } };

    $sf->menu('menu', '/', ['cap' => 'missions.use', 'order' => 35, 'icon' => 'puzzle']);

    /* ---------- setup values for every installation: map keys etc. are kept once on the hub (core.sysfera.ru) ---------- */
    $setupKeys = ['yandex_maps_key', 'mapbox_key', 'tactify_url'];
    $mdir = dirname($sf->dir) . '/messenger/lib/';
    foreach (['Util', 'Hub', 'Client'] as $lf) if (is_file($mdir . "$lf.php")) require_once $mdir . "$lf.php";
    if (class_exists(\SysferaMsg\HubServer::class)) {
        // the hub answers installations (user 0) with the values the administrator typed on the hub
        \SysferaMsg\HubServer::register('mis.setup', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) use ($sf, $setupKeys) { $o = []; foreach ($setupKeys as $k) $o[$k] = $sf->setting('hub_' . $k, ''); return $o; }, true);
        require_once __DIR__ . '/lib/Network.php';
        // the network library (hub): sites publish / withdraw their shared missions, everybody reads, readers can report
        \SysferaMsg\HubServer::register('mis.publish', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) {
            $in = $c->in; $local = (int)($in['local_id'] ?? 0); if ($local <= 0) throw new \SysferaMsg\HubError('bad_request', 400);
            if ((int)$hdb->val("SELECT COUNT(*) FROM {p}mis_net WHERE owner_sid = ? AND updated_at > (NOW() - INTERVAL 1 DAY)", [$c->sid()]) >= Network::PUBLISH_PER_DAY) throw new \SysferaMsg\HubError('post_rate', 429);
            $clean = Network::sanitize((array)($in['mission'] ?? [])); if (!$clean) throw new \SysferaMsg\HubError('bad_mission', 422);
            Network::store($hdb, 'site:' . $c->domain, (string)$local, $c->sid(), $clean, Network::s($in['author'] ?? '', 100), Network::s($in['category'] ?? '', 100), Network::s($in['category_ru'] ?? '', 100));
            return ['ok' => true];
        });
        \SysferaMsg\HubServer::register('mis.withdraw', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) {
            $hdb->q('DELETE FROM {p}mis_net WHERE source = ? AND ext_id = ? AND owner_sid = ?', ['site:' . $c->domain, (string)(int)($c->in['local_id'] ?? 0), $c->sid()]);
            return ['ok' => true];
        });
        \SysferaMsg\HubServer::register('mis.report', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) {
            $id = (int)($c->in['id'] ?? 0); if (!$hdb->val('SELECT 1 FROM {p}mis_net WHERE id = ?', [$id])) throw new \SysferaMsg\HubError('not_found', 404);
            $hdb->q('INSERT IGNORE INTO {p}mis_net_reports (net_id, sid) VALUES (?,?)', [$id, $c->sid()]);
            $n = (int)$hdb->val('SELECT COUNT(*) FROM {p}mis_net_reports WHERE net_id = ?', [$id]);
            $hdb->q('UPDATE {p}mis_net SET reports = ?, hidden = IF(? >= ?, 1, hidden), updated_at = updated_at WHERE id = ?', [$n, $n, Network::HIDE_AFTER_REPORTS, $id]);
            return ['ok' => true];
        });
        \SysferaMsg\HubServer::register('mis.library', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) use ($sf) {
            if (function_exists('mis_tactify_tick')) mis_tactify_tick();
            if (isset($c->q['id'])) {
                $r = $hdb->row('SELECT id, source, title, type, category, category_ru, description, author, tasks_n, content, updated_at FROM {p}mis_net WHERE id = ? AND hidden = 0', [(int)$c->q['id']]);
                if (!$r) throw new \SysferaMsg\HubError('not_found', 404);
                $r['content'] = json_decode((string)$r['content'], true) ?: [];
                return ['mission' => $r];
            }
            $page = max(1, (int)($c->q['page'] ?? 1));
            [$rows, $total] = Network::search($hdb, mb_substr((string)($c->q['q'] ?? ''), 0, 100), mb_substr((string)($c->q['cat'] ?? ''), 0, 100), (string)($c->q['src'] ?? ''), 24, ($page - 1) * 24);
            return ['rows' => $rows, 'total' => $total];
        }, true);
    }
    $isHub = fn(): bool => Hooks::filter('messenger.hub', null) !== null;
    /** hub: pull from / push to tactify.ru at most every 15 minutes (called when somebody opens the library, by the cron and by the "sync now" button) */
    $tactifySync = function (bool $force = false) use ($sf, $db, $isHub): ?array {
        if (!$isHub() || !class_exists(\SysferaMissions\Network::class)) return null;
        $base = \SysferaMissions\Network::baseUrl($sf->setting('hub_tactify_url', ''));
        $secret = $sf->setting('hub_tactify_secret', '');
        if ($base === '' || strlen($secret) < 32) return null;
        if (!$force && time() - (int)$sf->setting('hub_tactify_last', '0') < \SysferaMissions\Network::PULL_EVERY) return null;
        $sf->setSetting('hub_tactify_last', (string)time());
        try { $r = \SysferaMissions\Network::syncTactify($db, $base, $secret, $force ? 25 : 8); } catch (\Throwable $e) { $r = ['pulled' => 0, 'pushed' => 0, 'removed' => 0, 'error' => 'exception']; App::log('missions tactify sync ' . $e); }
        $sf->setSetting('hub_tactify_status', json_encode($r + ['at' => date('Y-m-d H:i:s')]));
        return $r;
    };
    $GLOBALS['mis_tactify_tick'] = $tactifySync;
    if (!function_exists('mis_tactify_tick')) { function mis_tactify_tick(): void { if (isset($GLOBALS['mis_tactify_tick'])) ($GLOBALS['mis_tactify_tick'])(false); } }
    /** a site: the connection to the hub (null when this site is not connected) */
    $netClient = function () { $c = Hooks::filter('messenger.client', null); return $c && method_exists($c, 'ready') && $c->ready() ? $c : null; };
    /** the values in use: what the administrator typed on this site, else what the hub sent (cached for an hour) */
    $setup = function () use ($sf, $isHub, $setupKeys): array {
        $own = []; foreach ($setupKeys as $k) $own[$k] = $isHub() ? $sf->setting('hub_' . $k, '') : $sf->setting('local_' . $k, '');
        $file = SF_ROOT . '/storage/missions-setup.json';
        $cached = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
        $age = is_file($file) ? time() - (int)filemtime($file) : PHP_INT_MAX;
        if (!$isHub() && $age >= 3600 && !(is_file($file . '.try') && time() - (int)filemtime($file . '.try') < 300)) {
            @file_put_contents($file . '.try', (string)time());
            $c = Hooks::filter('messenger.client', null);
            if ($c && method_exists($c, 'ready') && $c->ready()) {
                try { $r = $c->call('GET', 'mis.setup', [], null, null, 4, '0'); if ($r['ok']) { $cached = array_intersect_key((array)$r['data'], array_flip($setupKeys)); @file_put_contents($file, json_encode($cached)); } } catch (\Throwable) {}
            }
        }
        $out = []; foreach ($setupKeys as $k) $out[$k] = $own[$k] !== '' ? $own[$k] : (string)($cached[$k] ?? '');
        return $out + ['_from_hub' => $cached ? 1 : 0, '_age' => is_file($file) ? $age : -1];
    };

    /* ---------- access ---------- */
    $mine = fn(int $id): ?array => $db->row('SELECT * FROM {p}mis_missions WHERE id = ? AND user_id = ?', [$id, $uid()]);
    /** a mission shared to the library goes to the network library on the hub (and comes back when it is made private or deleted); never blocks the page */
    $netSync = function (int $id, bool $drop = false) use ($db, $uid, $netClient, $sf): void {
        $c = $netClient(); if (!$c) return;
        $m = $db->row('SELECT m.*, c.name AS cat, c.name_ru AS cat_ru, u.name AS author FROM {p}mis_missions m LEFT JOIN {p}mis_categories c ON c.id = m.category_id LEFT JOIN {p}users u ON u.id = m.user_id WHERE m.id = ? AND m.user_id = ?', [$id, $uid()]);
        if (!$m) return;
        try {
            if ($m['shared'] && !$drop) { $r = $c->call('POST', 'mis.publish', [], ['local_id' => $id, 'mission' => Mission::export($db, $id), 'category' => (string)$m['cat'], 'category_ru' => (string)$m['cat_ru'], 'author' => (string)$m['author']], null, null, 5); if ($r['ok']) $db->q('UPDATE {p}mis_missions SET net_pub = 1 WHERE id = ?', [$id]); }
            elseif ($m['net_pub']) { $r = $c->call('POST', 'mis.withdraw', [], ['local_id' => $id], null, null, 5); if ($r['ok']) $db->q('UPDATE {p}mis_missions SET net_pub = 0 WHERE id = ?', [$id]); }
        } catch (\Throwable $e) { App::log('missions network ' . $e->getMessage()); }
    };
    /** the teams the user belongs to (only when Teams is installed) */
    $myTeams = function () use ($db, $uid, $teamsOn): array {
        if (!$teamsOn()) return [];
        try { return $db->rows('SELECT t.id, t.name FROM {p}teams_members tm JOIN {p}teams_teams t ON t.id = tm.team_id WHERE tm.user_id = ? AND t.archived = 0 ORDER BY t.name', [$uid()]); } catch (\Throwable) { return []; }
    };
    /** a mission the user may look at: own, in the library, or shared with one of their teams → [mission, own?] */
    $access = function (int $id) use ($db, $uid, $mine, $teamsOn): ?array {
        if ($m = $mine($id)) return [$m, true];
        $m = $db->row('SELECT * FROM {p}mis_missions WHERE id = ?', [$id]);
        if (!$m) return null;
        if ($m['shared']) return [$m, false];
        if ($teamsOn()) {
            try { if ($db->val('SELECT 1 FROM {p}mis_shares s JOIN {p}teams_members tm ON tm.team_id = s.team_id JOIN {p}teams_teams t ON t.id = s.team_id AND t.archived = 0 WHERE s.mission_id = ? AND tm.user_id = ?', [$id, $uid()])) return [$m, false]; } catch (\Throwable) {}
        }
        return null;
    };

    /* ---------- helpers ---------- */
    $subtree = function (int $id) use ($db): array {
        $ids = [$id]; $queue = [$id];
        while ($queue) { $p = array_shift($queue); foreach ($db->rows('SELECT id FROM {p}mis_tasks WHERE parent_id = ?', [$p]) as $r) { $ids[] = (int)$r['id']; $queue[] = (int)$r['id']; } }
        return $ids;
    };
    $note = fn(int $user, ?int $mid, ?int $tid, string $kind, string $body) => $db->q('INSERT INTO {p}mis_notes (user_id, mission_id, task_id, kind, body) VALUES (?,?,?,?,?)', [$user, $mid, $tid, $kind, mb_substr($body, 0, 500)]);
    /** delete the stored files of tasks */
    $dropFiles = function (array $taskIds) use ($db, $store): void {
        if (!$taskIds) return; $in = implode(',', array_map('intval', $taskIds));
        foreach ($db->rows("SELECT id, blob_key FROM {p}mis_files WHERE task_id IN ($in)") as $f) if (preg_match('/^[a-f0-9]{32}$/', (string)$f['blob_key'])) @unlink("$store/{$f['blob_key']}.bin");
        $db->q("DELETE FROM {p}mis_files WHERE task_id IN ($in)");
    };
    /** completed: event log, a note for the team members, and (when the owner asked) a post in the Activity feed */
    $completed = function (array $m) use ($sf, $db, $uid, $note, $inLang): void {
        $sf->log('mission.completed', ['id' => (int)$m['id'], 'title' => $m['title']]);
        $owner = (int)$m['user_id'];
        try {
            $who = (string)$db->val('SELECT name FROM {p}users WHERE id = ?', [$owner]);
            foreach ($db->rows('SELECT DISTINCT tm.user_id FROM {p}mis_shares s JOIN {p}teams_members tm ON tm.team_id = s.team_id WHERE s.mission_id = ? AND tm.user_id <> ?', [$m['id'], $owner]) as $r)
                $note((int)$r['user_id'], (int)$m['id'], null, 'team', $inLang((string)$m['lang'], fn() => $sf->t('note_team_done', ['title' => $m['title'], 'name' => $who])));
        } catch (\Throwable) {}   // Teams is not installed
        if (!empty($m['announce'])) {
            $c = Hooks::filter('messenger.client', null);
            if ($c && method_exists($c, 'ready') && $c->ready()) {
                try {
                    if ($uid() === $owner) { $me = $sf->auth()->user() ?? []; $c->call('POST', 'me', [], ['name' => (string)($me['name'] ?? ''), 'email' => (string)($me['email'] ?? '')]); }
                    $n = (int)$db->val('SELECT COUNT(*) FROM {p}mis_tasks WHERE mission_id = ?', [$m['id']]);
                    [$title, $body] = Mission::announce((string)$m['title'], trim((string)$m['description']) !== '' ? (string)$m['description'] : $inLang((string)$m['lang'], fn() => $sf->t('announce_body', ['n' => $n])), $inLang((string)$m['lang'], fn() => $sf->t('announce_prefix')));
                    $c->call('POST', 'act.post', [], ['title' => $title, 'body' => $body !== '' ? $body : $title]);
                } catch (\Throwable) {}   // the feed is a bonus: never break the mission
            }
        }
    };
    $finish = function (int $mid) use ($db, $completed): void {
        $m = $db->row('SELECT * FROM {p}mis_missions WHERE id = ?', [$mid]); if (!$m) return;
        $db->q("UPDATE {p}mis_missions SET status = 'completed' WHERE id = ?", [$mid]); Mission::refreshLucky($db, $mid);
        if ($m['status'] !== 'completed') $completed($m);
    };
    $shopLinks = fn(): array => (array)Hooks::filter('tasktypes.shoplinks', [], $uid());

    /* ================= my missions ================= */
    $sf->route('*', '/', function () use ($sf, $db, $uid, $mine, $access, $cats, $path, $cron, $isAdmin, $finish, $dropFiles, $teamsOn, $types, $netSync) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? ''); $id = (int)($_POST['id'] ?? 0);
            if ($do === 'create') {
                $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
                if ($title === '') { flash($sf->t('err_title'), 'bad'); redirect($path()); }
                $cat = (int)($_POST['category_id'] ?? 0) ?: null;
                $db->q('INSERT INTO {p}mis_missions (user_id, title, type, category_id, description, due_date, lang, shared, hide_files) VALUES (?,?,?,?,?,?,?,?,?)', [$uid(), $title, mb_substr(trim((string)($_POST['type'] ?? '')), 0, 100), $cat,
                    mb_substr(trim((string)($_POST['description'] ?? '')), 0, 5000) ?: null, preg_match('/^\d{4}-\d\d-\d\d$/', (string)($_POST['due_date'] ?? '')) ? $_POST['due_date'] : null,
                    isset(I18n::languages()[(string)($_POST['lang'] ?? '')]) ? (string)$_POST['lang'] : '', ($_POST['vis'] ?? '') === 'public' ? 1 : 0, !empty($_POST['hide_files']) ? 1 : 0]);
                $mid = $db->insertId();
                $db->q("INSERT INTO {p}mis_tasks (mission_id, user_id, title, type) VALUES (?,?,?, 'note')", [$mid, $uid(), $sf->t('start_task')]);
                $sf->log('mission.created', ['id' => $mid]); $netSync($mid); redirect($path("m/$mid"));
            }
            if ($do === 'import') {
                $f = $_FILES['file'] ?? null;
                $raw = ($f && ($f['error'] ?? 1) === UPLOAD_ERR_OK && ($f['size'] ?? 0) <= 1048576) ? (string)file_get_contents($f['tmp_name']) : '';
                $d = json_decode($raw, true);
                try {
                    if (!is_array($d)) throw new RuntimeException('bad_file');
                    if (isset($d['missions']) && is_array($d['missions'])) { $n = 0; foreach (array_slice($d['missions'], 0, 100) as $one) if (is_array($one)) { try { Mission::import($db, $one, $uid(), $types()); $n++; } catch (RuntimeException) {} } if (!$n) throw new RuntimeException('bad_file'); flash($sf->t('imported_n', ['n' => $n])); redirect($path()); }
                    $mid = Mission::import($db, $d, $uid(), $types()); flash($sf->t('imported')); redirect($path("m/$mid"));
                }
                catch (RuntimeException $e) { flash($sf->t('err_file'), 'bad'); redirect($path()); }
            }
            if ($do === 'bookmark' && $id && $access($id)) {   // a star: own missions and the ones shared with the user's teams
                if ($db->val('SELECT 1 FROM {p}mis_bookmarks WHERE user_id = ? AND mission_id = ?', [$uid(), $id])) $db->q('DELETE FROM {p}mis_bookmarks WHERE user_id = ? AND mission_id = ?', [$uid(), $id]);
                else $db->q('INSERT IGNORE INTO {p}mis_bookmarks (user_id, mission_id) VALUES (?,?)', [$uid(), $id]);
                redirect(($_POST['from'] ?? '') === 'm' ? $path("m/$id") : $path());
            }
            if ($id && ($m = $mine($id))) {
                if ($do === 'delete') {
                    $netSync($id, true);
                    $tids = array_map(fn($r) => (int)$r['id'], $db->rows('SELECT id FROM {p}mis_tasks WHERE mission_id = ?', [$id])); $dropFiles($tids);
                    foreach (['mis_tasks', 'mis_shares', 'mis_bookmarks', 'mis_notes'] as $t) $db->q("DELETE FROM {p}$t WHERE mission_id = ?", [$id]);
                    $db->q('DELETE FROM {p}mis_missions WHERE id = ?', [$id]); flash($sf->t('deleted'));
                }
                elseif ($do === 'complete') $finish($id);
                elseif ($do === 'uncomplete') $db->q("UPDATE {p}mis_missions SET status = 'active' WHERE id = ?", [$id]);
                elseif ($do === 'duplicate') { $new = Mission::copy($db, $id, $uid()); $db->q('UPDATE {p}mis_missions SET title = ? WHERE id = ?', [mb_substr($m['title'], 0, 240) . ' (' . $sf->t('copy') . ')', $new]); flash($sf->t('imported')); redirect($path("m/$new")); }
            }
            redirect($path());
        }
        $st = in_array($_GET['status'] ?? '', ['active', 'completed'], true) ? $_GET['status'] : ''; $q = trim((string)($_GET['q'] ?? '')); $cat = (int)($_GET['cat'] ?? 0); $bm = !empty($_GET['bm']);
        $w = ' WHERE m.user_id = ?'; $a = [$uid()];
        if ($st) { $w .= ' AND m.status = ?'; $a[] = $st; }
        if ($cat) { $w .= ' AND m.category_id = ?'; $a[] = $cat; }
        if ($bm) { $w .= ' AND EXISTS (SELECT 1 FROM {p}mis_bookmarks b WHERE b.mission_id = m.id AND b.user_id = ?)'; $a[] = $uid(); }
        if ($q !== '') { $w .= ' AND (m.title LIKE ? OR m.type LIKE ?)'; $a[] = "%$q%"; $a[] = "%$q%"; }
        $rows = $db->rows("SELECT m.*, c.name AS cat, c.name_ru AS cat_ru, c.color, (SELECT COUNT(*) FROM {p}mis_tasks t WHERE t.mission_id = m.id) AS n, (SELECT COUNT(*) FROM {p}mis_tasks t WHERE t.mission_id = m.id AND t.completed = 1) AS done, (SELECT COALESCE(SUM(t.cost), 0) FROM {p}mis_tasks t WHERE t.mission_id = m.id) AS total,
            (SELECT COUNT(*) FROM {p}mis_bookmarks b WHERE b.mission_id = m.id AND b.user_id = " . (int)$uid() . ") AS starred
            FROM {p}mis_missions m LEFT JOIN {p}mis_categories c ON c.id = m.category_id$w ORDER BY m.status = 'completed', m.updated_at DESC LIMIT 200", $a);
        $withMe = [];
        if ($teamsOn()) {
            try { $withMe = $db->rows('SELECT DISTINCT m.id, m.title, m.status, u.name AS author, (SELECT COUNT(*) FROM {p}mis_bookmarks b WHERE b.mission_id = m.id AND b.user_id = ' . (int)$uid() . ') AS starred
                FROM {p}mis_shares s JOIN {p}teams_members tm ON tm.team_id = s.team_id AND tm.user_id = ? JOIN {p}teams_teams t ON t.id = s.team_id AND t.archived = 0 JOIN {p}mis_missions m ON m.id = s.mission_id AND m.user_id <> ? LEFT JOIN {p}users u ON u.id = m.user_id
                ORDER BY m.updated_at DESC LIMIT 50', [$uid(), $uid()]); } catch (\Throwable) {}
        }
        $sf->render('index', ['rows' => $rows, 'withMe' => $withMe, 'cats' => $cats(), 'st' => $st, 'q' => $q, 'cat' => $cat, 'bm' => $bm, 'langs' => I18n::languages(), 'cronUrl' => $isAdmin() ? 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . \Sysfera\App::basePath() . '/api/missions/remind?token=' . $cron() : ''], $sf->t('title'));
    }, ['cap' => 'missions.use']);

    /* ================= one mission ================= */
    $showMission = function (array $m, bool $own) use ($sf, $db, $cats, $uid, $types, $offered, $myTeams, $crmOn, $shopLinks, $on, $isAdmin, $setup): void {
        $tasks = $db->rows('SELECT * FROM {p}mis_tasks WHERE mission_id = ? ORDER BY id', [$m['id']]);
        $author = $own ? null : $db->val('SELECT name FROM {p}users WHERE id = ?', [$m['user_id']]);
        $files = []; if ($own || !$m['hide_files']) foreach ($db->rows('SELECT id, task_id, name, ext, size FROM {p}mis_files WHERE mission_id = ? ORDER BY id', [$m['id']]) as $f) $files[(int)$f['task_id']][] = $f;
        $shared = $own ? array_map('intval', array_column($db->rows('SELECT team_id FROM {p}mis_shares WHERE mission_id = ?', [$m['id']]), 'team_id')) : [];
        $contactName = [];   // names of the CRM contacts used in tasks (only for users who work with CRM)
        if ($crmOn()) foreach ($tasks as $t) { $d = json_decode((string)$t['type_data'], true) ?: []; if (!empty($d['contact_id'])) { $c = $sf->contacts()->find((int)$d['contact_id']); if ($c && ($c['owner_id'] === null || (int)$c['owner_id'] === $uid() || $isAdmin())) $contactName[(int)$d['contact_id']] = (string)$c['name']; } }
        $sf->render('mission', ['m' => $m, 'tasks' => $tasks, 'tree' => Mission::tree($tasks), 'lucky' => array_map('intval', (array)(json_decode((string)$m['lucky_way'], true) ?: [])), 'own' => $own, 'author' => $author,
            'cats' => $cats(), 'edit' => $own ? (int)($_GET['edit'] ?? 0) : 0, 'add' => $own ? (string)($_GET['add'] ?? '') : '', 'types' => $types(), 'offered' => $offered(), 'files' => $files, 'teams' => $own ? $myTeams() : [], 'sharedTeams' => $shared,
            'crm' => $crmOn(), 'contactName' => $contactName, 'shopLinks' => $own ? $shopLinks() : [], 'starred' => (bool)$db->val('SELECT 1 FROM {p}mis_bookmarks WHERE user_id = ? AND mission_id = ?', [$uid(), $m['id']]),
            'announceOn' => $own && $on('activity'), 'langs' => I18n::languages(), 'setup' => $setup()], $m['title']);
    };
    $sf->route('GET', '/m/{id}', function (array $a) use ($sf, $access, $showMission) {
        $x = $access((int)$a['id']); if (!$x) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $showMission($x[0], $x[1]);
    }, ['cap' => 'missions.use']);

    $sf->route('POST', '/m/{id}', function (array $a) use ($sf, $db, $uid, $mine, $path, $subtree, $finish, $types, $dropFiles, $store, $myTeams, $crmOn, $netSync) {
        $id = (int)$a['id']; $m = $mine($id); if (!$m) redirect($path());
        $do = (string)($_POST['do'] ?? ''); $back = $path("m/$id");
        $touch = fn() => $db->q('UPDATE {p}mis_missions SET updated_at = NOW() WHERE id = ?', [$id]);
        if ($do === 'update') {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
            if ($title === '') { flash($sf->t('err_title'), 'bad'); redirect($back); }
            $lang = (string)($_POST['lang'] ?? ''); if (!isset(I18n::languages()[$lang])) $lang = '';
            $db->q('UPDATE {p}mis_missions SET title = ?, type = ?, category_id = ?, description = ?, due_date = ?, shared = ?, lang = ?, announce = ?, hide_files = ? WHERE id = ?', [$title, mb_substr(trim((string)($_POST['type'] ?? '')), 0, 100), (int)($_POST['category_id'] ?? 0) ?: null,
                mb_substr(trim((string)($_POST['description'] ?? '')), 0, 5000) ?: null, preg_match('/^\d{4}-\d\d-\d\d$/', (string)($_POST['due_date'] ?? '')) ? $_POST['due_date'] : null, ($_POST['vis'] ?? (!empty($_POST['shared']) ? 'public' : 'private')) === 'public' ? 1 : 0, $lang, !empty($_POST['announce']) ? 1 : 0, !empty($_POST['hide_files']) ? 1 : 0, $id]);
            if ($myTeams() || isset($_POST['teams_form'])) {   // only the user's own teams can be chosen
                $mineIds = array_map('intval', array_column($myTeams(), 'id'));
                $db->q('DELETE FROM {p}mis_shares WHERE mission_id = ? AND team_id IN (' . ($mineIds ? implode(',', $mineIds) : '0') . ')', [$id]);
                foreach (array_unique(array_map('intval', (array)($_POST['teams'] ?? []))) as $tid) if (in_array($tid, $mineIds, true)) $db->q('INSERT IGNORE INTO {p}mis_shares (mission_id, team_id) VALUES (?,?)', [$id, $tid]);
            }
            flash($sf->t('saved'));
        } elseif ($do === 'task_save') {
            $all = $types(); $tid = (int)($_POST['task_id'] ?? 0); $type = Mission::type((string)($_POST['type'] ?? ''), $all); $def = TaskTypes::get($all, $type);
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
            if ($title === '') { flash($sf->t('err_title'), 'bad'); redirect($back); }
            $when = preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d/', (string)($_POST['scheduled_at'] ?? '')) ? str_replace('T', ' ', substr((string)$_POST['scheduled_at'], 0, 16)) . ':00' : null;
            $data = Mission::cleanData($type, (array)($_POST['td'] ?? []), $all);
            foreach ($def['fields'] as $fd) {
                if ($fd['kind'] === 'task' && !empty($data[$fd['key']]) && !$db->val('SELECT 1 FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [(int)$data[$fd['key']], $id])) unset($data[$fd['key']]);   // only a task of this mission
                if ($fd['kind'] === 'contact' && !$crmOn()) unset($data[$fd['key']]);
            }
            $f = [$title, $type, round((float)str_replace(',', '.', (string)($_POST['cost'] ?? 0)), 2), mb_substr(trim((string)($_POST['description'] ?? '')), 0, 5000) ?: null,
                mb_substr(trim((string)($_POST['result_good'] ?? '')), 0, 255), mb_substr(trim((string)($_POST['result_alt'] ?? '')), 0, 255), mb_substr(trim((string)($_POST['result_bad'] ?? '')), 0, 255),
                $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null, $when, $when && !empty($_POST['reminder']) ? 1 : 0];
            if ($tid && $db->val('SELECT 1 FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [$tid, $id])) {
                $db->q('UPDATE {p}mis_tasks SET title=?, type=?, cost=?, description=?, result_good=?, result_alt=?, result_bad=?, type_data=?, scheduled_at=?, reminder=?, reminder_sent=0 WHERE id = ?', [...$f, $tid]);
            } else {
                $pid = (int)($_POST['parent_id'] ?? 0); $br = Mission::branch((string)($_POST['branch'] ?? ''));
                if ($pid && !$db->val('SELECT 1 FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [$pid, $id])) $pid = 0;
                if ($pid && !$br) $br = 'good';
                $db->q('INSERT INTO {p}mis_tasks (title, type, cost, description, result_good, result_alt, result_bad, type_data, scheduled_at, reminder, mission_id, user_id, parent_id, branch) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [...$f, $id, $uid(), $pid ?: null, $pid ? $br : null]);
            }
            Mission::refreshLucky($db, $id); $touch(); flash($sf->t('saved'));
        } elseif ($do === 'task_delete') {
            $tid = (int)($_POST['task_id'] ?? 0);
            if ($tid && $db->val('SELECT 1 FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [$tid, $id])) {
                $ids = $subtree($tid); $in = implode(',', array_map('intval', $ids)); $dropFiles($ids);
                $db->q("DELETE FROM {p}mis_tasks WHERE id IN ($in)");
                Mission::refreshLucky($db, $id); $touch(); flash($sf->t('deleted'));
            }
        } elseif ($do === 'task_done') {
            $tid = (int)($_POST['task_id'] ?? 0); $t = $db->row('SELECT * FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [$tid, $id]);
            if ($t) {
                $now = $t['completed'] ? 0 : 1; $db->q('UPDATE {p}mis_tasks SET completed = ? WHERE id = ?', [$now, $tid]); $touch();
                $d = json_decode((string)$t['type_data'], true) ?: []; $code = TaskTypes::normalize((string)$t['type']);
                if ($now) {
                    if ($code === 'email' && !empty($d['to'])) { $e = $sf->mail($d['to'], (string)($d['subject'] ?? $t['title']), (string)($d['body'] ?? '')); flash($e === null ? $sf->t('mail_sent', ['to' => $d['to']]) : $sf->t('mail_fail', ['e' => $e]), $e === null ? 'ok' : 'bad'); }
                    elseif ($code === 'finish_mission') { $finish($id); flash($sf->t('mission_done')); }
                    elseif ($code === 'go_to_task' && !empty($d['target'])) redirect($back . '#t' . (int)$d['target']);
                }
                redirect($back . '#t' . $tid);
            }
        } elseif ($do === 'file_add') {   // attachment / photo of a task
            $tid = (int)($_POST['task_id'] ?? 0); $up = $_FILES['file'] ?? null;
            $f = $up && ($up['error'] ?? 4) === UPLOAD_ERR_OK ? Mission::file((string)$up['name'], (int)$up['size']) : null;
            $ok = $tid && $db->val('SELECT 1 FROM {p}mis_tasks WHERE id = ? AND mission_id = ?', [$tid, $id]);
            if (!$ok || !$f || !is_uploaded_file($up['tmp_name'])) flash($sf->t('e_file'), 'bad');
            elseif ((int)$db->val('SELECT COUNT(*) FROM {p}mis_files WHERE task_id = ?', [$tid]) >= Mission::MAX_FILES_PER_TASK) flash($sf->t('e_files_max', ['n' => Mission::MAX_FILES_PER_TASK]), 'bad');
            elseif (!is_dir($store) && !@mkdir($store, 0755, true)) flash($sf->t('e_storage'), 'bad');
            else {
                $key = bin2hex(random_bytes(16));
                if (!move_uploaded_file($up['tmp_name'], "$store/$key.bin")) flash($sf->t('e_storage'), 'bad');
                else { $db->q('INSERT INTO {p}mis_files (mission_id, task_id, user_id, name, ext, size, blob_key) VALUES (?,?,?,?,?,?,?)', [$id, $tid, $uid(), $f[0], $f[1], (int)$up['size'], $key]); flash($sf->t('uploaded')); }
            }
            redirect($back . '#t' . $tid);
        } elseif ($do === 'file_del') {
            $f = $db->row('SELECT * FROM {p}mis_files WHERE id = ? AND mission_id = ?', [(int)($_POST['file_id'] ?? 0), $id]);
            if ($f) { if (preg_match('/^[a-f0-9]{32}$/', (string)$f['blob_key'])) @unlink("$store/{$f['blob_key']}.bin"); $db->q('DELETE FROM {p}mis_files WHERE id = ?', [$f['id']]); flash($sf->t('deleted')); }
            redirect($back . ($f ? '#t' . (int)$f['task_id'] : ''));
        } elseif ($do === 'complete') { $finish($id); flash($sf->t('mission_done')); }
        elseif ($do === 'uncomplete') { $db->q("UPDATE {p}mis_missions SET status = 'active' WHERE id = ?", [$id]); }
        if (in_array($do, ['update', 'task_save', 'task_delete'], true)) $netSync($id);
        redirect($back);
    }, ['cap' => 'missions.use']);

    /* attachments are served through PHP (storage/ is not reachable from the web); only to people who may see the mission */
    $sf->route('GET', '/file/{fid}', function (array $a) use ($sf, $db, $access, $store) {
        $f = $db->row('SELECT * FROM {p}mis_files WHERE id = ?', [(int)$a['fid']]);
        $ax = $f ? $access((int)$f['mission_id']) : null;
        if (!$f || !$ax || (!$ax[1] && $ax[0]['hide_files']) || !preg_match('/^[a-f0-9]{32}$/', (string)$f['blob_key']) || !is_file("$store/{$f['blob_key']}.bin")) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $img = in_array($f['ext'], Mission::INLINE, true);
        header('Content-Type: ' . (Mission::FILES[$f['ext']] ?? 'application/octet-stream')); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'");
        header('Content-Disposition: ' . ($img ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)$f['name']) . '"'); header('Content-Length: ' . (string)filesize("$store/{$f['blob_key']}.bin"));
        readfile("$store/{$f['blob_key']}.bin");
    }, ['cap' => 'missions.use']);

    $sf->route('GET', '/m/{id}/export', function (array $a) use ($sf, $db, $access) {
        $x = $access((int)$a['id']);
        if (!$x) { http_response_code(404); echo e($sf->t('not_found')); return; }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mission-' . (int)$x[0]['id'] . '.json"');
        echo json_encode(Mission::export($db, (int)$x[0]['id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }, ['cap' => 'missions.use']);

    /* ================= library of shared missions ================= */
    $sf->route('GET', '/library', function () use ($sf, $db, $cats, $netClient) {
        $q = trim((string)($_GET['q'] ?? '')); $cat = (int)($_GET['cat'] ?? 0); $src = (string)($_GET['src'] ?? ''); $net = null;
        if (in_array($src, ['net', 'tactify', 'sites'], true)) {   // the network library kept on the hub
            $net = ['rows' => [], 'total' => 0, 'err' => null, 'page' => max(1, (int)($_GET['page'] ?? 1))];
            $c = $netClient();
            if (!$c) $net['err'] = 'not_connected';
            else {
                $catName = $cat ? (string)$db->val('SELECT name FROM {p}mis_categories WHERE id = ?', [$cat]) : '';
                $r = $c->call('GET', 'mis.library', ['q' => $q, 'cat' => $catName, 'src' => $src === 'net' ? '' : $src, 'page' => $net['page']], null, null, 6, '0');
                if ($r['ok']) { $net['rows'] = (array)($r['data']['rows'] ?? []); $net['total'] = (int)($r['data']['total'] ?? 0); } else $net['err'] = (string)$r['error'];
            }
            $sf->render('library', ['rows' => [], 'cats' => $cats(), 'q' => $q, 'cat' => $cat, 'src' => $src, 'net' => $net], $sf->t('library'));
            return;
        }
        $w = ' WHERE m.shared = 1'; $a = [];
        if ($cat) { $w .= ' AND m.category_id = ?'; $a[] = $cat; }
        if ($q !== '') { $w .= ' AND (m.title LIKE ? OR m.type LIKE ? OR m.description LIKE ?)'; array_push($a, "%$q%", "%$q%", "%$q%"); }
        $rows = $db->rows("SELECT m.id, m.user_id, m.title, m.type, m.description, m.updated_at, c.name AS cat, c.name_ru AS cat_ru, c.color, u.name AS author, (SELECT COUNT(*) FROM {p}mis_tasks t WHERE t.mission_id = m.id) AS n
            FROM {p}mis_missions m LEFT JOIN {p}mis_categories c ON c.id = m.category_id LEFT JOIN {p}users u ON u.id = m.user_id$w ORDER BY m.updated_at DESC LIMIT 100", $a);
        $sf->render('library', ['rows' => $rows, 'cats' => $cats(), 'q' => $q, 'cat' => $cat, 'src' => '', 'net' => null], $sf->t('library'));
    }, ['cap' => 'missions.use']);
    /** a mission of the network library: preview, copy to my missions, report */
    $sf->route('*', '/library/net/{id}', function (array $a) use ($sf, $db, $uid, $path, $netClient, $types) {
        $c = $netClient(); $id = (int)$a['id']; $m = null;
        if ($c) { $r = $c->call('GET', 'mis.library', ['id' => $id], null, null, 6, '0'); if ($r['ok']) $m = $r['data']['mission'] ?? null; }
        if (!$m) { http_response_code(404); echo e($sf->t('not_found')); return; }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? '');
            if ($do === 'import') {
                try { $new = Mission::import($db, (array)$m['content'], $uid(), $types()); flash($sf->t('imported')); redirect($path("m/$new")); } catch (\RuntimeException) { flash($sf->t('e_file'), 'bad'); }
            } elseif ($do === 'report') { $c->call('POST', 'mis.report', [], ['id' => $id], null, null, 5); flash($sf->t('net_reported')); }
            redirect($path("library/net/$id"));
        }
        $sf->render('netmission', ['m' => $m, 'types' => $types(), 'id' => $id], (string)$m['title']);
    }, ['cap' => 'missions.use']);
    $sf->route('GET', '/library/{id}', function (array $a) use ($sf, $access, $showMission) {
        $x = $access((int)$a['id']);
        if (!$x) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $showMission($x[0], $x[1]);
    }, ['cap' => 'missions.use']);
    $sf->route('POST', '/library/{id}', function (array $a) use ($sf, $db, $uid, $path, $access) {
        $x = $access((int)$a['id']);
        if ($x && ($_POST['do'] ?? '') === 'import') { $new = Mission::copy($db, (int)$x[0]['id'], $uid()); flash($sf->t('imported')); redirect($path("m/$new")); }
        redirect($path('library'));
    }, ['cap' => 'missions.use']);

    /* ================= notifications (in-app list) ================= */
    $sf->route('*', '/notes', function () use ($sf, $db, $uid, $path) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? ''); $id = (int)($_POST['id'] ?? 0);
            if ($do === 'read' && $id) $db->q('UPDATE {p}mis_notes SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL', [$id, $uid()]);
            elseif ($do === 'read_all') $db->q('UPDATE {p}mis_notes SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$uid()]);
            elseif ($do === 'clear') $db->q('DELETE FROM {p}mis_notes WHERE user_id = ? AND read_at IS NOT NULL', [$uid()]);
            redirect($path('notes'));
        }
        $rows = $db->rows('SELECT * FROM {p}mis_notes WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$uid()]);
        $sf->render('notes', ['rows' => $rows], $sf->t('nav_notes'));
    }, ['cap' => 'missions.use']);

    /* ================= export everything ================= */
    $sf->route('GET', '/export', function () use ($sf, $db, $uid) {
        $all = [];
        foreach ($db->rows('SELECT id FROM {p}mis_missions WHERE user_id = ? ORDER BY id', [$uid()]) as $r) $all[] = Mission::export($db, (int)$r['id']);
        header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="missions.json"');
        echo json_encode(['format' => 'sysfera-missions', 'version' => 1, 'missions' => $all], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }, ['cap' => 'missions.use']);

    /* ================= setup: map keys, library address ================= */
    $sf->route('*', '/setup', function () use ($sf, $db, $path, $setup, $isHub, $setupKeys, $tactifySync) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? '');
            if ($isHub() && $do === 'net_sync') { $r = $tactifySync(true); flash($r === null ? $sf->t('net_sync_off') : ($r['error'] ? $sf->t('net_sync_err', ['e' => $r['error']]) : $sf->t('net_synced', ['a' => $r['pulled'], 'b' => $r['pushed'], 'c' => $r['removed']])), $r && !$r['error'] ? 'ok' : 'bad'); }
            elseif ($isHub() && $do === 'net_hide') $db->q('UPDATE {p}mis_net SET hidden = 1 - hidden, updated_at = updated_at WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
            elseif ($isHub() && $do === 'net_delete') $db->q('DELETE FROM {p}mis_net WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
            elseif ($do === 'refresh') { @unlink(SF_ROOT . '/storage/missions-setup.json'); @unlink(SF_ROOT . '/storage/missions-setup.json.try'); flash($sf->t('setup_refreshed')); }
            else {
                foreach ($setupKeys as $k) {
                    $v = trim((string)($_POST[$k] ?? ''));
                    if ($k === 'tactify_url' && $v !== '' && !preg_match('~^https://[^\s<>"\']+$~i', $v)) continue;       // only a https address
                    if ($k !== 'tactify_url' && !preg_match('/^[A-Za-z0-9._\-]{0,300}$/', $v)) continue;                   // keys: letters, digits . _ -
                    $sf->setSetting(($isHub() ? 'hub_' : 'local_') . $k, $v);
                }
                $sec = trim((string)($_POST['tactify_secret'] ?? ''));   // the key of the Tactify API: kept on the hub only, never sent to sites
                if ($isHub() && $sec !== '' && preg_match('/^[A-Za-z0-9]{32,200}$/', $sec)) $sf->setSetting('hub_tactify_secret', $sec);
                flash($sf->t('saved'));
            }
            redirect($path('setup'));
        }
        $own = []; foreach ($setupKeys as $k) $own[$k] = $sf->setting(($isHub() ? 'hub_' : 'local_') . $k, '');
        $net = $isHub() ? ['has' => strlen($sf->setting('hub_tactify_secret', '')) >= 32, 'status' => json_decode($sf->setting('hub_tactify_status', ''), true) ?: null,
            'rows' => $db->rows('SELECT id, source, title, author, hidden, reports, updated_at FROM {p}mis_net ORDER BY updated_at DESC LIMIT 40'), 'total' => (int)$db->val('SELECT COUNT(*) FROM {p}mis_net')] : null;
        $sf->render('setup', ['hub' => $isHub(), 'own' => $own, 'now' => $setup(), 'keys' => $setupKeys, 'net' => $net], $sf->t('setup'));
    }, ['admin' => true]);

    /* ================= categories (administrator) ================= */
    $sf->route('*', '/categories', function () use ($sf, $db, $path, $cats) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? ''); $id = (int)($_POST['id'] ?? 0);
            $f = fn() => [mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100), mb_substr(trim((string)($_POST['name_ru'] ?? '')), 0, 100), preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#6b7280', (int)($_POST['sort_order'] ?? 0)];
            if ($do === 'add' && ($v = $f())[0] !== '') $db->q('INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) VALUES (?,?,?,?)', $v);
            elseif ($do === 'save' && $id && ($v = $f())[0] !== '') $db->q('UPDATE {p}mis_categories SET name=?, name_ru=?, color=?, sort_order=? WHERE id = ?', [...$v, $id]);
            elseif ($do === 'delete' && $id) { $db->q('UPDATE {p}mis_missions SET category_id = NULL WHERE category_id = ?', [$id]); $db->q('DELETE FROM {p}mis_categories WHERE id = ?', [$id]); }
            flash($sf->t('saved')); redirect($path('categories'));
        }
        $sf->render('categories', ['cats' => $cats()], $sf->t('categories'));
    }, ['admin' => true]);

    /* ================= reminders (cron) ================= */
    // hosting cron, every minute:  curl -s "https://<site>/api/missions/remind?token=<token shown on the Missions page for administrators>"
    $sf->api('GET', 'missions/remind', function () use ($sf, $db, $finish, $note, $inLang, $tactifySync) {
        header('Content-Type: application/json; charset=utf-8');
        $tok = $sf->setting('cron_token', '');
        if ($tok === '' || !hash_equals($tok, (string)($_GET['token'] ?? ''))) { http_response_code(401); echo '{"error":"unauthorized"}'; return; }
        $tactifySync();
        $due = $db->rows('SELECT t.*, m.title AS mtitle, m.lang AS mlang, u.email AS owner_email, u.lang AS ulang FROM {p}mis_tasks t JOIN {p}mis_missions m ON m.id = t.mission_id JOIN {p}users u ON u.id = t.user_id
            WHERE t.reminder = 1 AND t.reminder_sent = 0 AND t.completed = 0 AND t.scheduled_at IS NOT NULL AND t.scheduled_at <= NOW() ORDER BY t.scheduled_at LIMIT 100');
        $sent = 0; $types = TaskTypes::all($db, null, true);
        foreach ($due as $t) {
            $d = json_decode((string)$t['type_data'], true) ?: []; $code = TaskTypes::normalize((string)$t['type']); $l = (string)($t['mlang'] ?: ($t['ulang'] ?? ''));
            $db->q('UPDATE {p}mis_tasks SET reminder_sent = 1 WHERE id = ?', [$t['id']]);   // first: never send twice
            if ($code === 'email' && !empty($d['to'])) { $e = $sf->mail($d['to'], (string)($d['subject'] ?? $t['title']), (string)($d['body'] ?? '')); if ($e === null) { $db->q('UPDATE {p}mis_tasks SET completed = 1 WHERE id = ?', [$t['id']]); $sent++; } }
            elseif ($code === 'finish_mission') { $db->q('UPDATE {p}mis_tasks SET completed = 1 WHERE id = ?', [$t['id']]); $finish((int)$t['mission_id']); $sent++; }
            else {
                $label = TaskTypes::label(TaskTypes::get($types, $code), $l ?: 'en');
                $note((int)$t['user_id'], (int)$t['mission_id'], (int)$t['id'], 'reminder', $inLang($l, fn() => $sf->t('note_reminder', ['task' => $t['title'], 'mission' => $t['mtitle'], 'when' => substr((string)$t['scheduled_at'], 0, 16)])));
                if ($t['owner_email']) {
                    $link = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . \Sysfera\App::basePath() . '/p/missions/m/' . (int)$t['mission_id'];
                    $r = $inLang($l, fn() => [$sf->t('remind_subject', ['task' => $t['title']]), $sf->t('remind_text', ['task' => $t['title'], 'mission' => $t['mtitle'], 'type' => $label, 'when' => (string)$t['scheduled_at'], 'url' => $link])]);
                    if ($sf->mail($t['owner_email'], $r[0], $r[1]) === null) $sent++;
                }
            }
        }
        echo json_encode(['ok' => true, 'due' => count($due), 'sent' => $sent]);
    });

    $sf->on('dashboard.widgets', function (array $w) use ($sf, $db) {
        if (!$sf->can('missions.use')) return $w;
        $u = $sf->auth()->id();
        $open = (int)$db->val("SELECT COUNT(*) FROM {p}mis_missions WHERE user_id = ? AND status = 'active'", [$u]);
        $late = (int)$db->val('SELECT COUNT(*) FROM {p}mis_tasks WHERE user_id = ? AND completed = 0 AND scheduled_at IS NOT NULL AND scheduled_at < NOW()', [$u]);
        $new = (int)$db->val('SELECT COUNT(*) FROM {p}mis_notes WHERE user_id = ? AND read_at IS NULL', [$u]);
        $w[] = '<b>' . e($sf->t('title')) . '</b>: ' . e($sf->t('widget', ['open' => $open])) . ($late ? ' · <span class="bad-t">' . e($sf->t('widget_late', ['n' => $late])) . '</span>' : '')
            . ($new ? ' · <a href="' . e($sf->url('/notes')) . '">🔔 ' . e($sf->t('widget_new', ['n' => $new])) . '</a>' : '') . ' — <a href="' . e($sf->url('/')) . '">' . e($sf->t('open')) . '</a>';
        return $w;
    });
};
