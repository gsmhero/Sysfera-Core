<?php
declare(strict_types=1);

use SysferaTeams\Tm;

/** Sysfera Teams — team collaboration: teams and roles, chat channels, task board, calendar, files. */
return function (\Sysfera\Plugin $sf): void {
    require_once __DIR__ . '/lib/Tm.php';

    $db = $sf->db();
    $uid = fn() => $sf->auth()->id();
    $isSite = fn() => $sf->auth()->isAdmin();
    $self = fn(string $p = '') => '/p/teams/' . ltrim($p, '/');
    $int = fn($v) => ctype_digit((string)$v) && (int)$v > 0 ? (int)$v : null;
    $txt = fn($k, $max = 190) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $color = fn($v) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? (string)$v : '#4f46e5';
    $now = fn() => date('Y-m-d H:i:s');
    $store = SF_ROOT . '/storage/teams';
    $json = function (array $d, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); };

    $sf->menu('menu', '/', ['cap' => 'teams.use', 'order' => 18, 'icon' => 'users']);
    \Sysfera\Hooks::on('demo.caps', fn(array $caps) => $caps);

    $route = function (string $method, string $path, callable $fn) use ($sf): void { $sf->route($method, $path, $fn, ['cap' => 'teams.use']); };
    $page = fn(string $view, array $vars, string $title) => $sf->render($view, $vars, $title);

    /* ---------- access ---------- */
    /** role of the current user in a team (site administrators act as owners everywhere), null = not a member */
    $role = function (int $tid) use ($db, $uid, $isSite): ?string {
        $r = $db->val('SELECT role FROM {p}teams_members WHERE team_id = ? AND user_id = ?', [$tid, $uid()]);
        return $r ?: ($isSite() ? 'owner' : null);
    };
    $team = function (int $tid) use ($db, $role): ?array {
        $t = $db->row('SELECT * FROM {p}teams_teams WHERE id = ?', [$tid]); if (!$t) return null;
        $t['role'] = $role($tid); return $t;
    };
    /** open a team page: returns the team or redirects */
    $open = function (array $p, ?string $action = 'read') use ($team, $sf, $self): array {
        $t = $team((int)$p['id']);
        if (!$t || ($t['role'] === null && $t['privacy'] !== 'open')) { flash($sf->t('e_not_found'), 'bad'); redirect($self()); }
        if ($action !== null && !Tm::can($t['role'], $action)) { flash($sf->t($t['role'] === null ? 'e_join_first' : 'e_denied'), 'bad'); redirect($self('t/' . $t['id'])); }
        return $t;
    };
    $channels = function (int $tid, string $role) use ($db, $uid): array {
        $adm = Tm::can($role, 'channel') ? 1 : 0; $u = $uid();
        return $db->rows('SELECT c.*, (SELECT COUNT(*) FROM {p}teams_messages m WHERE m.channel_id = c.id AND m.deleted = 0 AND m.id > COALESCE(r.last_id, 0) AND (m.user_id IS NULL OR m.user_id <> ?)) AS unread,
            (SELECT COUNT(*) FROM {p}teams_mentions mn WHERE mn.channel_id = c.id AND mn.user_id = ? AND mn.message_id > COALESCE(r.last_id, 0)) AS mentions
            FROM {p}teams_channels c LEFT JOIN {p}teams_reads r ON r.channel_id = c.id AND r.user_id = ?
            WHERE c.team_id = ? AND c.archived = 0 AND (c.is_private = 0 OR ? = 1 OR EXISTS (SELECT 1 FROM {p}teams_channel_members cm WHERE cm.channel_id = c.id AND cm.user_id = ?)) ORDER BY c.sort, c.id',
            [$u, $u, $u, $tid, $adm, $u]);
    };
    $members = fn(int $tid) => $db->rows('SELECT u.id, u.name, u.email, m.role FROM {p}teams_members m JOIN {p}users u ON u.id = m.user_id WHERE m.team_id = ? ORDER BY FIELD(m.role, \'owner\', \'admin\', \'member\'), u.name', [$tid]);
    $nameMap = function (int $tid) use ($members): array { $m = []; foreach ($members($tid) as $r) $m[(int)$r['id']] = (string)$r['name']; return $m; };
    $layout = fn(array $t, string $tab, array $vars = []) => $vars + ['t' => $t, 'tab' => $tab, 'chans' => ($t['role'] !== null ? $channels((int)$t['id'], (string)$t['role']) : [])];

    $addMember = function (int $tid, int $userId, string $r = 'member') use ($db): void {
        $db->q('INSERT IGNORE INTO {p}teams_members (team_id, user_id, role) VALUES (?,?,?)', [$tid, $userId, $r]);
    };

    /* =============================== teams list =============================== */
    $route('GET', '/', function () use ($db, $uid, $page, $sf, $isSite) {
        $mine = $db->rows('SELECT t.*, m.role, (SELECT COUNT(*) FROM {p}teams_members x WHERE x.team_id = t.id) AS n,
            (SELECT COUNT(*) FROM {p}teams_messages g JOIN {p}teams_channels c ON c.id = g.channel_id LEFT JOIN {p}teams_reads r ON r.channel_id = c.id AND r.user_id = ?
              WHERE c.team_id = t.id AND c.archived = 0 AND g.deleted = 0 AND g.id > COALESCE(r.last_id, 0) AND (g.user_id IS NULL OR g.user_id <> ?)
                AND (c.is_private = 0 OR EXISTS (SELECT 1 FROM {p}teams_channel_members cm WHERE cm.channel_id = c.id AND cm.user_id = ?))) AS unread
            FROM {p}teams_teams t JOIN {p}teams_members m ON m.team_id = t.id AND m.user_id = ? WHERE t.archived = 0 ORDER BY t.name', [$uid(), $uid(), $uid(), $uid()]);
        $open = $db->rows("SELECT t.*, (SELECT COUNT(*) FROM {p}teams_members x WHERE x.team_id = t.id) AS n FROM {p}teams_teams t
            WHERE t.archived = 0 AND t.privacy = 'open' AND NOT EXISTS (SELECT 1 FROM {p}teams_members m WHERE m.team_id = t.id AND m.user_id = ?) ORDER BY t.name LIMIT 50", [$uid()]);
        $page('index', ['mine' => $mine, 'open' => $open, 'canCreate' => $sf->can('teams.create') || $isSite()], $sf->t('menu'));
    });

    $route('POST', '/team/save', function () use ($db, $sf, $uid, $txt, $color, $self, $addMember, $isSite) {
        if (!($sf->can('teams.create') || $isSite())) { flash($sf->t('e_denied'), 'bad'); redirect($self()); }
        $name = $txt('name', 120); if ($name === '') { flash($sf->t('e_name'), 'bad'); redirect($self()); }
        $db->q('INSERT INTO {p}teams_teams (name, descr, color, privacy, created_by) VALUES (?,?,?,?,?)', [$name, $txt('descr', 500) ?: null, $color($_POST['color'] ?? ''), ($_POST['privacy'] ?? '') === 'open' ? 'open' : 'private', $uid()]);
        $tid = $db->insertId(); $addMember($tid, $uid(), 'owner');
        $db->q('INSERT INTO {p}teams_channels (team_id, name, topic, sort, created_by) VALUES (?,?,?,?,?)', [$tid, $sf->t('general'), $sf->t('general_topic'), 10, $uid()]);
        foreach ([['col_todo', 0], ['col_doing', 0], ['col_done_name', 1]] as $i => [$k, $d]) $db->q('INSERT INTO {p}teams_cols (team_id, name, is_done, sort) VALUES (?,?,?,?)', [$tid, $sf->t($k), $d, ($i + 1) * 10]);
        flash($sf->t('team_created')); redirect($self("t/$tid"));
    });

    $route('POST', '/team/{id}/join', function (array $p) use ($team, $addMember, $uid, $sf, $self) {
        $t = $team((int)$p['id']);
        if ($t && $t['privacy'] === 'open' && !$t['archived'] && $t['role'] === null) { $addMember((int)$t['id'], $uid()); flash($sf->t('joined')); }
        redirect($self('t/' . (int)$p['id']));
    });

    $route('POST', '/t/{id}/leave', function (array $p) use ($db, $open, $uid, $sf, $self) {
        $t = $open($p, 'leave');
        if ($t['role'] === 'owner' && (int)$db->val("SELECT COUNT(*) FROM {p}teams_members WHERE team_id = ? AND role = 'owner'", [$t['id']]) < 2) { flash($sf->t('e_last_owner'), 'bad'); redirect($self('t/' . $t['id'] . '/members')); }
        $db->q('DELETE FROM {p}teams_members WHERE team_id = ? AND user_id = ?', [$t['id'], $uid()]);
        $db->q('DELETE FROM {p}teams_channel_members WHERE user_id = ? AND channel_id IN (SELECT id FROM {p}teams_channels WHERE team_id = ?)', [$uid(), $t['id']]);
        flash($sf->t('left')); redirect($self());
    });

    $route('GET', '/t/{id}', function (array $p) use ($open, $channels, $self, $page, $sf, $layout, $db, $members, $role) {
        $t = $open($p, null);
        if ($t['role'] === null) { $page('preview', $layout($t, 'chat', ['n' => (int)$db->val('SELECT COUNT(*) FROM {p}teams_members WHERE team_id = ?', [$t['id']])]), $t['name']); return; }
        $ch = $channels((int)$t['id'], $t['role']);
        if ($ch) redirect($self('t/' . $t['id'] . '/c/' . $ch[0]['id']));
        $page('empty', $layout($t, 'chat'), $t['name']);
    });

    /* =============================== chat =============================== */
    /** may the current user read this channel? returns the channel row or null */
    $chan = function (array $t, int $cid) use ($db, $uid): ?array {
        $c = $db->row('SELECT * FROM {p}teams_channels WHERE id = ? AND team_id = ? AND archived = 0', [$cid, $t['id']]);
        if (!$c) return null;
        if ($c['is_private'] && !Tm::can($t['role'], 'channel') && !$db->val('SELECT 1 FROM {p}teams_channel_members WHERE channel_id = ? AND user_id = ?', [$cid, $uid()])) return null;
        return $c;
    };
    $fmt = function (array $m, array $react, array $t, array $names) use ($uid): array {
        $mine = (int)$m['user_id'] === $uid(); $r = [];
        foreach ($react[(int)$m['id']] ?? [] as $e => $users) $r[] = ['e' => $e, 'n' => count($users), 'me' => in_array($uid(), $users, true)];
        return ['id' => (int)$m['id'], 'uid' => (int)$m['user_id'], 'name' => $names[(int)$m['user_id']] ?? ($m['uname'] ?? '—'), 'at' => substr((string)$m['created_at'], 0, 16), 'mine' => $mine,
            'body' => $m['deleted'] ? null : (string)$m['body'], 'deleted' => (bool)$m['deleted'], 'edited' => (bool)$m['edited_at'], 'pinned' => (bool)$m['pinned'],
            'reply' => $m['reply_to'] ? ['id' => (int)$m['reply_to'], 'name' => (string)($m['rname'] ?? ''), 'text' => Tm::clip((string)($m['rbody'] ?? ''))] : null,
            'file' => !$m['deleted'] && $m['file_id'] ? ['id' => (int)$m['file_id'], 'name' => (string)$m['fname'], 'size' => (int)$m['fsize'], 'img' => in_array((string)$m['fext'], Tm::INLINE, true)] : null,
            'react' => $r, 'can_del' => !$m['deleted'] && ($mine || Tm::can($t['role'], 'moderate'))];
    };
    /** messages of a channel as arrays for the client */
    $load = function (array $t, int $cid, string $where, array $args, int $limit, string $order = 'DESC') use ($db, $fmt, $nameMap): array {
        $rows = $db->rows('SELECT m.*, u.name AS uname, ru.name AS rname, rm.body AS rbody, f.name AS fname, f.size AS fsize, f.ext AS fext
            FROM {p}teams_messages m LEFT JOIN {p}users u ON u.id = m.user_id LEFT JOIN {p}teams_messages rm ON rm.id = m.reply_to LEFT JOIN {p}users ru ON ru.id = rm.user_id LEFT JOIN {p}teams_files f ON f.id = m.file_id
            WHERE m.channel_id = ? ' . $where . ' ORDER BY m.id ' . $order . ' LIMIT ' . max(1, min(200, $limit)), [$cid, ...$args]);
        if ($order === 'DESC') $rows = array_reverse($rows);
        $react = [];
        if ($rows) {
            $ids = array_map(fn($r) => (int)$r['id'], $rows);
            foreach ($db->rows('SELECT message_id, user_id, emoji FROM {p}teams_reactions WHERE message_id IN (' . implode(',', $ids) . ')') as $x) $react[(int)$x['message_id']][$x['emoji']][] = (int)$x['user_id'];
        }
        $names = $nameMap((int)$t['id']);
        return array_map(fn($m) => $fmt($m, $react, $t, $names), $rows);
    };
    $markRead = function (int $cid) use ($db, $uid): void {
        $max = (int)$db->val('SELECT COALESCE(MAX(id), 0) FROM {p}teams_messages WHERE channel_id = ?', [$cid]);
        $db->q('INSERT INTO {p}teams_reads (channel_id, user_id, last_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_id = GREATEST(last_id, VALUES(last_id))', [$cid, $uid(), $max]);
    };

    $route('GET', '/t/{id}/c/{cid}', function (array $p) use ($open, $chan, $load, $markRead, $page, $layout, $sf, $self, $db, $members, $uid) {
        $t = $open($p, 'read'); $c = $chan($t, (int)$p['cid']);
        if (!$c) { flash($sf->t('e_not_found'), 'bad'); redirect($self('t/' . $t['id'])); }
        $msgs = $load($t, (int)$c['id'], '', [], 60);
        $lastRead = (int)$db->val('SELECT last_id FROM {p}teams_reads WHERE channel_id = ? AND user_id = ?', [$c['id'], $uid()]);
        $markRead((int)$c['id']);
        $pins = array_values(array_filter($msgs, fn($m) => $m['pinned']));
        $page('channel', $layout($t, 'chat', ['c' => $c, 'msgs' => $msgs, 'pins' => $pins, 'lastRead' => $lastRead, 'people' => $members((int)$t['id']), 'me' => $uid(), 'emoji' => Tm::EMOJI, 'maxFile' => Tm::MAX_FILE,
            'chMembers' => $c['is_private'] ? array_map('intval', array_column($db->rows('SELECT user_id FROM {p}teams_channel_members WHERE channel_id = ?', [$c['id']]), 'user_id')) : []]), '# ' . $c['name']);
    });

    $route('GET', '/t/{id}/c/{cid}/poll', function (array $p) use ($open, $chan, $load, $markRead, $json, $db) {
        $t = $open($p, 'read'); $c = $chan($t, (int)$p['cid']);
        if (!$c) { $json(['ok' => false], 404); return; }
        $after = max(0, (int)($_GET['after'] ?? 0));
        $new = $load($t, (int)$c['id'], 'AND m.id > ?', [$after], 100, 'ASC');
        $upd = $after > 0 ? $load($t, (int)$c['id'], 'AND m.id <= ?', [$after], 40) : [];
        if ($new) $markRead((int)$c['id']);
        $json(['ok' => true, 'new' => $new, 'updates' => $upd]);
    });

    $route('POST', '/t/{id}/c/{cid}/send', function (array $p) use ($open, $chan, $db, $uid, $sf, $json, $store, $load, $nameMap, $now, $markRead) {
        $t = $open($p, 'post'); $c = $chan($t, (int)$p['cid']);
        if (!$c) { $json(['ok' => false, 'error' => $sf->t('e_not_found')], 404); return; }
        if ((int)$db->val('SELECT COUNT(*) FROM {p}teams_messages WHERE user_id = ? AND created_at > ?', [$uid(), date('Y-m-d H:i:s', time() - 60)]) >= 30) { $json(['ok' => false, 'error' => $sf->t('e_rate')], 429); return; }
        $up = $_FILES['file'] ?? null; $hasFile = $up && ($up['error'] ?? 4) === UPLOAD_ERR_OK;
        [$body, $err] = Tm::text((string)($_POST['body'] ?? ''), $hasFile);
        if ($err) { $json(['ok' => false, 'error' => $sf->t($err)], 422); return; }
        $fileId = null;
        if ($hasFile) {
            if (!Tm::can($t['role'], 'upload') || \Sysfera\Demo::on()) { $json(['ok' => false, 'error' => $sf->t('e_denied')], 403); return; }
            $f = Tm::file((string)$up['name'], (int)$up['size']);
            if (!$f || !is_uploaded_file($up['tmp_name'])) { $json(['ok' => false, 'error' => $sf->t('e_file')], 422); return; }
            if (!is_dir($store) && !@mkdir($store, 0755, true)) { $json(['ok' => false, 'error' => $sf->t('e_storage')], 500); return; }
            $stored = bin2hex(random_bytes(16));
            if (!move_uploaded_file($up['tmp_name'], "$store/$stored.bin")) { $json(['ok' => false, 'error' => $sf->t('e_storage')], 500); return; }
            $db->q('INSERT INTO {p}teams_files (team_id, channel_id, user_id, name, ext, size, stored) VALUES (?,?,?,?,?,?,?)', [$t['id'], $c['id'], $uid(), $f[0], $f[1], (int)$up['size'], $stored]);
            $fileId = $db->insertId();
        }
        $reply = ctype_digit((string)($_POST['reply_to'] ?? '')) ? (int)$_POST['reply_to'] : null;
        if ($reply && !$db->val('SELECT 1 FROM {p}teams_messages WHERE id = ? AND channel_id = ?', [$reply, $c['id']])) $reply = null;
        $db->q('INSERT INTO {p}teams_messages (channel_id, user_id, reply_to, body, file_id) VALUES (?,?,?,?,?)', [$c['id'], $uid(), $reply, $body !== '' ? $body : null, $fileId]);
        $mid = $db->insertId();
        foreach (Tm::mentions($body, $nameMap((int)$t['id']), $uid()) as $m) $db->q('INSERT IGNORE INTO {p}teams_mentions (message_id, user_id, channel_id) VALUES (?,?,?)', [$mid, $m, $c['id']]);
        $markRead((int)$c['id']);
        \Sysfera\Hooks::do('teams.message.created', $mid);
        $json(['ok' => true, 'message' => $load($t, (int)$c['id'], 'AND m.id = ?', [$mid], 1)[0] ?? null]);
    });

    $route('POST', '/t/{id}/c/{cid}/msg/{mid}/{act}', function (array $p) use ($open, $chan, $db, $uid, $sf, $json, $load) {
        $t = $open($p, 'read'); $c = $chan($t, (int)$p['cid']);
        $m = $c ? $db->row('SELECT * FROM {p}teams_messages WHERE id = ? AND channel_id = ?', [(int)$p['mid'], $c['id']]) : null;
        if (!$m) { $json(['ok' => false, 'error' => $sf->t('e_not_found')], 404); return; }
        $mine = (int)$m['user_id'] === $uid(); $act = $p['act'];
        if ($act === 'react') {
            $e = (string)($_POST['emoji'] ?? '');
            if (!Tm::can($t['role'], 'react') || !in_array($e, Tm::EMOJI, true) || $m['deleted']) { $json(['ok' => false], 403); return; }
            $has = $db->val('SELECT 1 FROM {p}teams_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?', [$m['id'], $uid(), $e]);
            $has ? $db->q('DELETE FROM {p}teams_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?', [$m['id'], $uid(), $e]) : $db->q('INSERT INTO {p}teams_reactions (message_id, user_id, emoji) VALUES (?,?,?)', [$m['id'], $uid(), $e]);
        } elseif ($act === 'edit') {
            [$b, $err] = Tm::text((string)($_POST['body'] ?? ''), (bool)$m['file_id']);
            if (!$mine || $m['deleted'] || $err) { $json(['ok' => false, 'error' => $sf->t($err ?: 'e_denied')], 403); return; }
            $db->q('UPDATE {p}teams_messages SET body = ?, edited_at = NOW() WHERE id = ?', [$b !== '' ? $b : null, $m['id']]);
        } elseif ($act === 'delete') {
            if (!($mine || Tm::can($t['role'], 'moderate'))) { $json(['ok' => false, 'error' => $sf->t('e_denied')], 403); return; }
            $db->q('UPDATE {p}teams_messages SET deleted = 1, pinned = 0 WHERE id = ?', [$m['id']]);
        } elseif ($act === 'pin') {
            if (!Tm::can($t['role'], 'moderate') || $m['deleted']) { $json(['ok' => false, 'error' => $sf->t('e_denied')], 403); return; }
            $db->q('UPDATE {p}teams_messages SET pinned = ? WHERE id = ?', [$m['pinned'] ? 0 : 1, $m['id']]);
        } else { $json(['ok' => false], 404); return; }
        $json(['ok' => true, 'message' => $load($t, (int)$c['id'], 'AND m.id = ?', [$m['id']], 1)[0] ?? null]);
    });

    $route('POST', '/t/{id}/channel/save', function (array $p) use ($open, $db, $txt, $uid, $sf, $self, $int) {
        $t = $open($p, 'channel'); $id = $int($_POST['id'] ?? '');
        $name = trim((string)preg_replace('/[^\p{L}\p{N}_-]+/u', '-', mb_strtolower($txt('name', 60))), '-');
        if ($name === '') { flash($sf->t('e_name'), 'bad'); redirect($self('t/' . $t['id'])); }
        $priv = !empty($_POST['private']) ? 1 : 0;
        if ($id) {
            if (!$db->val('SELECT 1 FROM {p}teams_channels WHERE id = ? AND team_id = ?', [$id, $t['id']])) redirect($self('t/' . $t['id']));
            $db->q('UPDATE {p}teams_channels SET name = ?, topic = ? WHERE id = ?', [$name, $txt('topic', 250) ?: null, $id]);
        } else {
            $db->q('INSERT INTO {p}teams_channels (team_id, name, topic, is_private, sort, created_by) VALUES (?,?,?,?,?,?)', [$t['id'], $name, $txt('topic', 250) ?: null, $priv, Tm::nextSort($db->rows('SELECT sort FROM {p}teams_channels WHERE team_id = ?', [$t['id']])), $uid()]);
            $id = $db->insertId();
            if ($priv) {
                $ok = array_map('intval', array_column($db->rows('SELECT user_id FROM {p}teams_members WHERE team_id = ?', [$t['id']]), 'user_id'));
                foreach (array_unique(array_merge([$uid()], array_map('intval', (array)($_POST['members'] ?? [])))) as $m) if (in_array($m, $ok, true)) $db->q('INSERT IGNORE INTO {p}teams_channel_members (channel_id, user_id) VALUES (?,?)', [$id, $m]);
            }
        }
        redirect($self('t/' . $t['id'] . '/c/' . $id));
    });

    $route('POST', '/t/{id}/channel/{cid}/archive', function (array $p) use ($open, $db, $self, $sf) {
        $t = $open($p, 'channel');
        if ((int)$db->val('SELECT COUNT(*) FROM {p}teams_channels WHERE team_id = ? AND archived = 0', [$t['id']]) > 1) $db->q('UPDATE {p}teams_channels SET archived = 1 WHERE id = ? AND team_id = ?', [(int)$p['cid'], $t['id']]);
        else flash($sf->t('e_last_channel'), 'bad');
        redirect($self('t/' . $t['id']));
    });

    /* =============================== files =============================== */
    $route('GET', '/t/{id}/files', function (array $p) use ($open, $db, $page, $layout, $sf, $uid) {
        $t = $open($p, 'read');
        $rows = $db->rows('SELECT f.*, u.name AS uname, c.name AS cname, c.is_private FROM {p}teams_files f LEFT JOIN {p}users u ON u.id = f.user_id LEFT JOIN {p}teams_channels c ON c.id = f.channel_id
            WHERE f.team_id = ? AND (c.id IS NULL OR c.is_private = 0 OR ? = 1 OR EXISTS (SELECT 1 FROM {p}teams_channel_members cm WHERE cm.channel_id = c.id AND cm.user_id = ?)) ORDER BY f.id DESC LIMIT 200',
            [$t['id'], Tm::can($t['role'], 'channel') ? 1 : 0, $uid()]);
        $page('files', $layout($t, 'files', ['files' => $rows, 'maxFile' => Tm::MAX_FILE]), $sf->t('files'));
    });

    $route('POST', '/t/{id}/files/upload', function (array $p) use ($open, $db, $uid, $sf, $store, $self) {
        $t = $open($p, 'upload'); $up = $_FILES['file'] ?? null;
        $f = $up && ($up['error'] ?? 4) === UPLOAD_ERR_OK ? Tm::file((string)$up['name'], (int)$up['size']) : null;
        if (\Sysfera\Demo::on() || !$f || !is_uploaded_file($up['tmp_name'])) { flash($sf->t('e_file'), 'bad'); redirect($self('t/' . $t['id'] . '/files')); }
        if (!is_dir($store) && !@mkdir($store, 0755, true)) { flash($sf->t('e_storage'), 'bad'); redirect($self('t/' . $t['id'] . '/files')); }
        $stored = bin2hex(random_bytes(16));
        if (!move_uploaded_file($up['tmp_name'], "$store/$stored.bin")) { flash($sf->t('e_storage'), 'bad'); redirect($self('t/' . $t['id'] . '/files')); }
        $db->q('INSERT INTO {p}teams_files (team_id, channel_id, user_id, name, ext, size, stored) VALUES (?,NULL,?,?,?,?,?)', [$t['id'], $uid(), $f[0], $f[1], (int)$up['size'], $stored]);
        flash($sf->t('uploaded')); redirect($self('t/' . $t['id'] . '/files'));
    });

    $route('POST', '/t/{id}/files/{fid}/delete', function (array $p) use ($open, $db, $uid, $store, $self) {
        $t = $open($p, 'read'); $f = $db->row('SELECT * FROM {p}teams_files WHERE id = ? AND team_id = ?', [(int)$p['fid'], $t['id']]);
        if ($f && ((int)$f['user_id'] === $uid() || Tm::can($t['role'], 'moderate'))) {
            $db->q('UPDATE {p}teams_messages SET file_id = NULL WHERE file_id = ?', [$f['id']]); $db->q('DELETE FROM {p}teams_files WHERE id = ?', [$f['id']]);
            if (preg_match('/^[a-f0-9]{32}$/', (string)$f['stored'])) @unlink("$store/{$f['stored']}.bin");
        }
        redirect($self('t/' . $t['id'] . '/files'));
    });

    /** download: only members of the team (and of a private channel) */
    $route('GET', '/file/{fid}', function (array $p) use ($db, $team, $uid, $store, $sf, $isSite) {
        $f = $db->row('SELECT * FROM {p}teams_files WHERE id = ?', [(int)$p['fid']]); $t = $f ? $team((int)$f['team_id']) : null;
        $ok = $f && $t && Tm::can($t['role'], 'read') && $t['role'] !== null;
        if ($ok && $f['channel_id']) { $c = $db->row('SELECT * FROM {p}teams_channels WHERE id = ?', [$f['channel_id']]);
            if ($c && $c['is_private'] && !Tm::can($t['role'], 'channel') && !$db->val('SELECT 1 FROM {p}teams_channel_members WHERE channel_id = ? AND user_id = ?', [$c['id'], $uid()])) $ok = false; }
        $path = $ok && preg_match('/^[a-f0-9]{32}$/', (string)$f['stored']) ? "$store/{$f['stored']}.bin" : '';
        if ($path === '' || !is_file($path)) { http_response_code(404); echo 'Not found'; return; }
        $inline = in_array($f['ext'], Tm::INLINE, true) && !empty($_GET['inline']);
        header('Content-Type: ' . (Tm::FILES[$f['ext']] ?? 'application/octet-stream')); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', (string)$f['name']) . '"; filename*=UTF-8\'\'' . rawurlencode((string)$f['name']));
        header('Content-Length: ' . filesize($path)); header('Cache-Control: private, max-age=3600'); readfile($path);
    });

    /* =============================== task board =============================== */
    $route('GET', '/t/{id}/board', function (array $p) use ($open, $db, $page, $layout, $sf, $members, $int, $uid) {
        $t = $open($p, 'read');
        $cols = $db->rows('SELECT * FROM {p}teams_cols WHERE team_id = ? ORDER BY sort, id', [$t['id']]);
        $f = ['mine' => !empty($_GET['mine']), 'q' => trim((string)($_GET['q'] ?? ''))];
        $w = 't.team_id = ?'; $a = [$t['id']];
        if ($f['mine']) { $w .= ' AND t.assignee_id = ?'; $a[] = $uid(); }
        if ($f['q'] !== '') { $w .= ' AND t.title LIKE ?'; $a[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%'; }
        $tasks = $db->rows("SELECT t.*, u.name AS aname FROM {p}teams_tasks t LEFT JOIN {p}users u ON u.id = t.assignee_id WHERE $w ORDER BY t.prio DESC, (t.due IS NULL), t.due, t.id LIMIT 1000", $a);
        $by = array_fill_keys(array_map(fn($c) => (int)$c['id'], $cols), []);
        foreach ($tasks as $x) if (isset($by[(int)$x['col_id']])) $by[(int)$x['col_id']][] = $x;
        $edit = ($id = $int($_GET['edit'] ?? '')) ? $db->row('SELECT * FROM {p}teams_tasks WHERE id = ? AND team_id = ?', [$id, $t['id']]) : null;
        $page('board', $layout($t, 'board', ['cols' => $cols, 'by' => $by, 'people' => $members((int)$t['id']), 'f' => $f, 'edit' => $edit, 'today' => date('Y-m-d')]), $sf->t('board'));
    });

    $route('POST', '/t/{id}/task/save', function (array $p) use ($open, $db, $txt, $uid, $int, $sf, $self, $nameMap, $now) {
        $t = $open($p, 'task'); $id = $int($_POST['id'] ?? ''); $title = $txt('title');
        $col = $int($_POST['col_id'] ?? '');
        if ($title === '' || !$col || !$db->val('SELECT 1 FROM {p}teams_cols WHERE id = ? AND team_id = ?', [$col, $t['id']])) { flash($sf->t('e_title'), 'bad'); redirect($self('t/' . $t['id'] . '/board')); }
        $as = $int($_POST['assignee_id'] ?? ''); if ($as && !isset($nameMap((int)$t['id'])[$as])) $as = null;
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['due'] ?? '')) ? $_POST['due'] : null;
        $prio = in_array((int)($_POST['prio'] ?? 1), Tm::PRIO, true) ? (int)$_POST['prio'] : 1;
        $isDone = (int)$db->val('SELECT is_done FROM {p}teams_cols WHERE id = ?', [$col]);
        if ($id && $db->val('SELECT 1 FROM {p}teams_tasks WHERE id = ? AND team_id = ?', [$id, $t['id']])) {
            $db->q('UPDATE {p}teams_tasks SET title=?, descr=?, col_id=?, assignee_id=?, due=?, prio=?, done_at = IF(? = 1, COALESCE(done_at, ?), NULL) WHERE id = ?', [$title, $txt('descr', 4000) ?: null, $col, $as, $due, $prio, $isDone, $now(), $id]);
        } else {
            $db->q('INSERT INTO {p}teams_tasks (team_id, col_id, title, descr, assignee_id, due, prio, created_by, done_at) VALUES (?,?,?,?,?,?,?,?,?)', [$t['id'], $col, $title, $txt('descr', 4000) ?: null, $as, $due, $prio, $uid(), $isDone ? $now() : null]);
        }
        redirect($self('t/' . $t['id'] . '/board'));
    });

    $route('POST', '/t/{id}/task/move', function (array $p) use ($open, $db, $int, $json, $now) {
        $t = $open($p, 'task'); $id = $int($_POST['id'] ?? ''); $col = $int($_POST['col'] ?? '');
        $ok = $id && $col && $db->val('SELECT 1 FROM {p}teams_cols WHERE id = ? AND team_id = ?', [$col, $t['id']]) && $db->val('SELECT 1 FROM {p}teams_tasks WHERE id = ? AND team_id = ?', [$id, $t['id']]);
        if ($ok) { $d = (int)$db->val('SELECT is_done FROM {p}teams_cols WHERE id = ?', [$col]); $db->q('UPDATE {p}teams_tasks SET col_id = ?, done_at = IF(? = 1, COALESCE(done_at, ?), NULL) WHERE id = ?', [$col, $d, $now(), $id]); }
        $json(['ok' => (bool)$ok]);
    });

    $route('POST', '/t/{id}/task/{tid}/delete', function (array $p) use ($open, $db, $self) {
        $t = $open($p, 'task'); $db->q('DELETE FROM {p}teams_tasks WHERE id = ? AND team_id = ?', [(int)$p['tid'], $t['id']]); redirect($self('t/' . $t['id'] . '/board'));
    });

    $route('POST', '/t/{id}/col/save', function (array $p) use ($open, $db, $txt, $int, $self, $sf) {
        $t = $open($p, 'channel'); $id = $int($_POST['id'] ?? ''); $name = $txt('name', 60);
        if ($name !== '') {
            if ($id) $db->q('UPDATE {p}teams_cols SET name = ?, is_done = ?, sort = ? WHERE id = ? AND team_id = ?', [$name, empty($_POST['is_done']) ? 0 : 1, (int)($_POST['sort'] ?? 0), $id, $t['id']]);
            else $db->q('INSERT INTO {p}teams_cols (team_id, name, is_done, sort) VALUES (?,?,?,?)', [$t['id'], $name, empty($_POST['is_done']) ? 0 : 1, Tm::nextSort($db->rows('SELECT sort FROM {p}teams_cols WHERE team_id = ?', [$t['id']]))]);
        }
        redirect($self('t/' . $t['id'] . '/board'));
    });

    $route('POST', '/t/{id}/col/{cid}/delete', function (array $p) use ($open, $db, $self, $sf) {
        $t = $open($p, 'channel');
        if ((int)$db->val('SELECT COUNT(*) FROM {p}teams_tasks WHERE col_id = ?', [(int)$p['cid']]) === 0 && (int)$db->val('SELECT COUNT(*) FROM {p}teams_cols WHERE team_id = ?', [$t['id']]) > 1) $db->q('DELETE FROM {p}teams_cols WHERE id = ? AND team_id = ?', [(int)$p['cid'], $t['id']]);
        else flash($sf->t('e_col_used'), 'bad');
        redirect($self('t/' . $t['id'] . '/board'));
    });

    /* =============================== calendar =============================== */
    $route('GET', '/t/{id}/calendar', function (array $p) use ($open, $db, $page, $layout, $sf, $int) {
        $t = $open($p, 'read'); $m = Tm::month((string)($_GET['m'] ?? ''), time());
        $from = $m['weeks'][0][0]['date'] . ' 00:00:00'; $to = end($m['weeks'])[6]['date'] . ' 23:59:59';
        $ev = $db->rows('SELECT e.*, u.name AS uname FROM {p}teams_events e LEFT JOIN {p}users u ON u.id = e.created_by WHERE e.team_id = ? AND e.starts <= ? AND COALESCE(e.ends, e.starts) >= ? ORDER BY e.starts', [$t['id'], $to, $from]);
        $up = $db->rows('SELECT e.*, u.name AS uname FROM {p}teams_events e LEFT JOIN {p}users u ON u.id = e.created_by WHERE e.team_id = ? AND COALESCE(e.ends, e.starts) >= NOW() ORDER BY e.starts LIMIT 8', [$t['id']]);
        $page('calendar', $layout($t, 'calendar', ['m' => $m, 'by' => Tm::byDay($ev), 'upcoming' => $up, 'edit' => ($id = $int($_GET['edit'] ?? '')) ? $db->row('SELECT * FROM {p}teams_events WHERE id = ? AND team_id = ?', [$id, $t['id']]) : null]), $sf->t('calendar'));
    });

    $route('POST', '/t/{id}/event/save', function (array $p) use ($open, $db, $txt, $uid, $int, $sf, $self) {
        $t = $open($p, 'event'); $id = $int($_POST['id'] ?? ''); $title = $txt('title');
        $start = Tm::when((string)($_POST['date'] ?? ''), (string)($_POST['time'] ?? ''));
        $end = ($_POST['date_end'] ?? '') !== '' ? Tm::when((string)$_POST['date_end'], (string)($_POST['time_end'] ?? '')) : null;
        if ($title === '' || !$start || ($end && $end < $start)) { flash($sf->t('e_event'), 'bad'); redirect($self('t/' . $t['id'] . '/calendar')); }
        $old = $id ? $db->row('SELECT * FROM {p}teams_events WHERE id = ? AND team_id = ?', [$id, $t['id']]) : null;
        if ($old) { if ((int)$old['created_by'] === $uid() || Tm::can($t['role'], 'moderate')) $db->q('UPDATE {p}teams_events SET title = ?, descr = ?, starts = ?, ends = ? WHERE id = ?', [$title, $txt('descr', 2000) ?: null, $start, $end, $id]); }
        else $db->q('INSERT INTO {p}teams_events (team_id, title, descr, starts, ends, created_by) VALUES (?,?,?,?,?,?)', [$t['id'], $title, $txt('descr', 2000) ?: null, $start, $end, $uid()]);
        redirect($self('t/' . $t['id'] . '/calendar?m=' . substr($start, 0, 7)));
    });

    $route('POST', '/t/{id}/event/{eid}/delete', function (array $p) use ($open, $db, $uid, $self) {
        $t = $open($p, 'event'); $e = $db->row('SELECT * FROM {p}teams_events WHERE id = ? AND team_id = ?', [(int)$p['eid'], $t['id']]);
        if ($e && ((int)$e['created_by'] === $uid() || Tm::can($t['role'], 'moderate'))) $db->q('DELETE FROM {p}teams_events WHERE id = ?', [$e['id']]);
        redirect($self('t/' . $t['id'] . '/calendar'));
    });

    /* =============================== members and team settings =============================== */
    $route('GET', '/t/{id}/members', function (array $p) use ($open, $db, $page, $layout, $sf, $members) {
        $t = $open($p, 'read'); $mem = $members((int)$t['id']);
        $others = Tm::can($t['role'], 'members') ? $db->rows("SELECT u.id, u.name, u.email FROM {p}users u WHERE u.status = 'active' AND u.id NOT IN (SELECT user_id FROM {p}teams_members WHERE team_id = ?) ORDER BY u.name LIMIT 500", [$t['id']]) : [];
        $page('members', $layout($t, 'members', ['mem' => $mem, 'others' => $others]), $sf->t('members'));
    });

    $route('POST', '/t/{id}/members/{do}', function (array $p) use ($open, $db, $int, $uid, $sf, $self, $addMember) {
        $t = $open($p, 'members'); $do = $p['do']; $u = $int($_POST['user_id'] ?? '');
        $cur = $u ? $db->val('SELECT role FROM {p}teams_members WHERE team_id = ? AND user_id = ?', [$t['id'], $u]) : null;
        $back = $self('t/' . $t['id'] . '/members');
        if ($do === 'add' && $u && !$cur && $db->val("SELECT 1 FROM {p}users WHERE id = ? AND status = 'active'", [$u])) { $addMember((int)$t['id'], $u); flash($sf->t('member_added')); }
        elseif ($do === 'role' && $u && $cur && Tm::can($t['role'], 'roles')) {
            $r = in_array($_POST['role'] ?? '', Tm::ROLES, true) ? $_POST['role'] : 'member';
            if ($cur === 'owner' && $r !== 'owner' && (int)$db->val("SELECT COUNT(*) FROM {p}teams_members WHERE team_id = ? AND role = 'owner'", [$t['id']]) < 2) flash($sf->t('e_last_owner'), 'bad');
            else { $db->q('UPDATE {p}teams_members SET role = ? WHERE team_id = ? AND user_id = ?', [$r, $t['id'], $u]); flash($sf->t('saved')); }
        } elseif ($do === 'remove' && $u && $cur && $cur !== 'owner' && ($cur === 'member' || Tm::can($t['role'], 'roles'))) {
            $db->q('DELETE FROM {p}teams_members WHERE team_id = ? AND user_id = ?', [$t['id'], $u]);
            $db->q('DELETE FROM {p}teams_channel_members WHERE user_id = ? AND channel_id IN (SELECT id FROM {p}teams_channels WHERE team_id = ?)', [$u, $t['id']]);
            flash($sf->t('member_removed'));
        }
        redirect($back);
    });

    $route('POST', '/t/{id}/settings', function (array $p) use ($open, $db, $txt, $color, $self, $sf) {
        $t = $open($p, 'edit_team');
        if (!empty($_POST['archive']) && Tm::can($t['role'], 'delete_team')) { $db->q('UPDATE {p}teams_teams SET archived = 1 WHERE id = ?', [$t['id']]); flash($sf->t('archived')); redirect($self()); }
        $name = $txt('name', 120);
        if ($name !== '') { $db->q('UPDATE {p}teams_teams SET name = ?, descr = ?, color = ?, privacy = ? WHERE id = ?', [$name, $txt('descr', 500) ?: null, $color($_POST['color'] ?? ''), ($_POST['privacy'] ?? '') === 'open' ? 'open' : 'private', $t['id']]); flash($sf->t('saved')); }
        redirect($self('t/' . $t['id'] . '/members'));
    });

    /* ---------- dashboard widget ---------- */
    \Sysfera\Hooks::on('dashboard.widgets', function (array $w) use ($sf, $db, $uid, $self) {
        if (!$sf->can('teams.use') || !$uid()) return $w;
        try {
            $u = $uid();
            $unread = (int)$db->val('SELECT COUNT(*) FROM {p}teams_messages g JOIN {p}teams_channels c ON c.id = g.channel_id JOIN {p}teams_members m ON m.team_id = c.team_id AND m.user_id = ?
                LEFT JOIN {p}teams_reads r ON r.channel_id = c.id AND r.user_id = ? WHERE c.archived = 0 AND g.deleted = 0 AND g.id > COALESCE(r.last_id, 0) AND (g.user_id IS NULL OR g.user_id <> ?)
                AND (c.is_private = 0 OR EXISTS (SELECT 1 FROM {p}teams_channel_members cm WHERE cm.channel_id = c.id AND cm.user_id = ?))', [$u, $u, $u, $u]);
            $tasks = (int)$db->val('SELECT COUNT(*) FROM {p}teams_tasks WHERE assignee_id = ? AND done_at IS NULL', [$u]);
        } catch (\Throwable $e) { return $w; }
        $w[] = '<div class="card"><div class="row between"><b>' . e($sf->t('menu')) . '</b><a href="' . e(url($self())) . '">' . e($sf->t('open')) . ' →</a></div><p style="margin:6px 0 0">'
            . e($sf->t('w_unread', ['n' => $unread])) . ' · ' . e($sf->t('w_tasks', ['n' => $tasks])) . '</p></div>';
        return $w;
    });
};
