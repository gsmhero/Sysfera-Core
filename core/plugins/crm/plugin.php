<?php
declare(strict_types=1);

use SysferaCrm\{Fields, Pipe};

/** Sysfera CRM — clients, deals (kanban), tasks, reports; pipelines, fields and permission profiles are configurable. */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Fields', 'Pipe'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db();
    $uid = fn() => $sf->auth()->id();
    $now = fn() => date('Y-m-d H:i:s');
    $self = fn(string $p = '') => '/p/crm/' . ltrim($p, '/');
    $int = fn($v) => ctype_digit((string)$v) && (int)$v > 0 ? (int)$v : null;
    $money = fn($v) => number_format((float)$v, 2, '.', ' ');
    $txt = fn($k, $max = 190) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $json = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
    $dec = fn($s) => (is_string($s) && $s !== '') ? (json_decode($s, true) ?: []) : [];
    $color = fn($v, $def = '#6b7280') => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? (string)$v : $def;

    $sf->menu('menu', '/', ['cap' => 'crm.use', 'order' => 15, 'icon' => 'users']);

    /* ---------- first visit: example pipelines and permission profiles ---------- */
    $seed = function () use ($db, $sf): void {
        static $done = false; if ($done) return; $done = true;
        if ((int)$db->val('SELECT COUNT(*) FROM {p}crm_pipelines')) return;
        $mk = function (string $name, string $color, array $stages) use ($db): void {
            $n = (int)$db->val('SELECT COUNT(*) FROM {p}crm_pipelines');
            $db->q('INSERT INTO {p}crm_pipelines (name, color, sort) VALUES (?,?,?)', [$name, $color, ($n + 1) * 10]);
            $pid = $db->insertId(); $i = 0;
            foreach ($stages as [$sn, $prob, $kind, $c]) $db->q('INSERT INTO {p}crm_stages (pipeline_id, name, prob, kind, color, sort) VALUES (?,?,?,?,?,?)', [$pid, $sn, $prob, $kind, $c, ++$i * 10]);
        };
        $mk($sf->t('seed_sales'), '#4f46e5', [[$sf->t('st_new'), 10, 'open', '#94a3b8'], [$sf->t('st_qualified'), 25, 'open', '#60a5fa'], [$sf->t('st_proposal'), 50, 'open', '#a78bfa'],
            [$sf->t('st_negotiation'), 75, 'open', '#f59e0b'], [$sf->t('st_won'), 100, 'won', '#16a34a'], [$sf->t('st_lost'), 0, 'lost', '#dc2626']]);
        $mk($sf->t('seed_projects'), '#0891b2', [[$sf->t('st_planned'), 10, 'open', '#94a3b8'], [$sf->t('st_progress'), 50, 'open', '#60a5fa'], [$sf->t('st_review'), 80, 'open', '#f59e0b'],
            [$sf->t('st_done'), 100, 'won', '#16a34a'], [$sf->t('st_cancelled'), 0, 'lost', '#dc2626']]);
        $first = 0;
        foreach (Pipe::defaultProfiles() as $name => $p) { $db->q('INSERT INTO {p}crm_profiles (name, perms) VALUES (?,?)', [$sf->t('prof_' . strtolower(str_replace(' ', '_', $name))), json_encode($p)]); $first = $first ?: $db->insertId(); }
        $sf->setSetting('default_profile', (string)$first);
        $sf->setSetting('reasons', json_encode([$sf->t('lr_price'), $sf->t('lr_competitor'), $sf->t('lr_no_budget'), $sf->t('lr_no_answer'), $sf->t('lr_other')], JSON_UNESCAPED_UNICODE));
        foreach ([['deal', 'source', $sf->t('fd_source'), 'select', [$sf->t('src_web'), $sf->t('src_call'), $sf->t('src_referral'), $sf->t('src_ads')]], ['contact', 'position', $sf->t('fd_position'), 'text', []]] as $i => [$e, $code, $label, $type, $opt])
            $db->q('INSERT INTO {p}crm_fields (entity, pipeline_id, code, label, type, options, sort) VALUES (?,NULL,?,?,?,?,?)', [$e, $code, $label, $type, json_encode($opt, JSON_UNESCAPED_UNICODE), ($i + 1) * 10]);
    };

    /* ---------- permission profile of the current user ---------- */
    $profile = function () use ($db, $sf, $uid): array {
        static $p = null; if ($p !== null) return $p;
        if ($sf->auth()->isAdmin()) return $p = Pipe::profile(['scope' => 'all'] + array_fill_keys(Pipe::PERMS, 1));
        $pid = (int)$db->val('SELECT profile_id FROM {p}crm_user_profile WHERE user_id = ?', [$uid()]) ?: (int)$sf->setting('default_profile', '0');
        $raw = $pid ? $db->val('SELECT perms FROM {p}crm_profiles WHERE id = ?', [$pid]) : null;
        return $p = Pipe::profile(is_string($raw) ? (json_decode($raw, true) ?: []) : []);
    };
    $need = function (string $perm) use ($profile, $sf, $self): void {
        if (!$profile()[$perm]) { flash($sf->t('e_denied'), 'bad'); redirect($self()); }
    };
    $back = fn(string $fallback) => redirect(str_starts_with((string)($_POST['back'] ?? ''), '/p/crm/') ? (string)$_POST['back'] : $fallback);

    $route = function (string $method, string $path, callable $fn) use ($sf, $seed): void {
        $sf->route($method, $path, function (array $p = []) use ($fn, $seed) { $seed(); return $fn($p); }, ['cap' => 'crm.use']);
    };
    $page = fn(string $view, array $vars, string $title, string $tab) => $sf->render($view, $vars + ['tab' => $tab, 'prof' => $profile()], $title);

    /* ---------- data helpers ---------- */
    $pipelines = fn() => $db->rows('SELECT * FROM {p}crm_pipelines WHERE active = 1 ORDER BY sort, id');
    $stagesOf = fn(int $pid) => $db->rows('SELECT * FROM {p}crm_stages WHERE pipeline_id = ? ORDER BY sort, id', [$pid]);
    $stageMap = function () use ($db): array { $m = []; foreach ($db->rows('SELECT * FROM {p}crm_stages') as $s) $m[(int)$s['id']] = $s; return $m; };
    $fieldDefs = function () use ($db, $dec): array {
        return array_map(function ($r) use ($dec) { $r['options'] = $dec($r['options']); $r['required'] = (bool)$r['required']; $r['pipeline_id'] = $r['pipeline_id'] === null ? null : (int)$r['pipeline_id']; return $r; },
            $db->rows('SELECT * FROM {p}crm_fields ORDER BY sort, id'));
    };
    $users = fn() => $db->rows("SELECT id, name FROM {p}users WHERE status = 'active' ORDER BY name");
    $log = function (?int $deal, ?int $contact, string $kind, string $body) use ($db, $uid): void {
        $db->q('INSERT INTO {p}crm_timeline (deal_id, contact_id, user_id, kind, body) VALUES (?,?,?,?,?)', [$deal, $contact, $uid() ?: null, $kind, mb_substr($body, 0, 3000)]);
    };
    $scopeSql = function (string $col) use ($profile, $uid): array {
        return $profile()['scope'] === 'all' ? ['', []] : [" AND ($col = ? OR $col IS NULL)", [$uid()]];
    };
    $reasons = fn() => array_values(array_filter((array)json_decode($sf->setting('reasons', '[]'), true), 'is_string'));
    $currency = fn() => $sf->setting('currency', 'RUB') ?: 'RUB';

    $dealRows = function (array $f, int $limit = 500) use ($db, $scopeSql): array {
        $w = ['1=1']; $a = [];
        if (!empty($f['pipeline'])) { $w[] = 'd.pipeline_id = ?'; $a[] = (int)$f['pipeline']; }
        if (!empty($f['owner'])) { $w[] = 'd.owner_id = ?'; $a[] = (int)$f['owner']; }
        if (!empty($f['contact'])) { $w[] = 'd.contact_id = ?'; $a[] = (int)$f['contact']; }
        if (!empty($f['status']) && in_array($f['status'], Pipe::KINDS, true)) { $w[] = 's.kind = ?'; $a[] = $f['status']; }
        if (!empty($f['tag'])) { $w[] = 'd.tags LIKE ?'; $a[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['tag']) . '%'; }
        if (!empty($f['q'])) { $l = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%'; $w[] = '(d.title LIKE ? OR c.name LIKE ? OR c.company LIKE ?)'; array_push($a, $l, $l, $l); }
        [$sc, $sa] = $scopeSql('d.owner_id');
        $limit = max(1, min(2000, $limit));
        return $db->rows('SELECT d.*, c.name AS contact_name, c.company AS contact_company, s.kind, s.prob, s.name AS stage_name, s.color AS stage_color, u.name AS owner_name,
            (SELECT COUNT(*) FROM {p}crm_tasks t WHERE t.deal_id = d.id AND t.done_at IS NULL) AS open_tasks,
            (SELECT MIN(t2.due_at) FROM {p}crm_tasks t2 WHERE t2.deal_id = d.id AND t2.done_at IS NULL AND t2.due_at IS NOT NULL) AS next_due
            FROM {p}crm_deals d JOIN {p}crm_stages s ON s.id = d.stage_id LEFT JOIN {p}contacts c ON c.id = d.contact_id LEFT JOIN {p}users u ON u.id = d.owner_id
            WHERE ' . implode(' AND ', $w) . $sc . ' ORDER BY d.updated_at DESC, d.id DESC LIMIT ' . $limit, [...$a, ...$sa]);
    };
    $getDeal = function (int $id) use ($db, $profile, $uid): ?array {
        $d = $db->row('SELECT d.*, s.kind, s.prob, s.name AS stage_name FROM {p}crm_deals d JOIN {p}crm_stages s ON s.id = d.stage_id WHERE d.id = ?', [$id]);
        return ($d && Pipe::visible($profile(), $d['owner_id'] === null ? null : (int)$d['owner_id'], $uid())) ? $d : null;
    };

    /** change the stage of a deal: closing dates, lost reason, history and hooks in one place */
    $setStage = function (array $deal, int $stageId, string $reason = '') use ($db, $log, $now, $stageMap): bool {
        $stages = $stageMap(); $to = $stages[$stageId] ?? null;
        if (!$to || (int)$to['pipeline_id'] !== (int)$deal['pipeline_id'] || $stageId === (int)$deal['stage_id']) return false;
        $closed = $to['kind'] !== 'open';
        $db->q('UPDATE {p}crm_deals SET stage_id = ?, closed_at = ?, lost_reason = ?, updated_at = ? WHERE id = ?',
            [$stageId, $closed ? $now() : null, $to['kind'] === 'lost' ? mb_substr($reason, 0, 190) : null, $now(), $deal['id']]);
        $log((int)$deal['id'], $deal['contact_id'] ? (int)$deal['contact_id'] : null, 'stage', ($stages[(int)$deal['stage_id']]['name'] ?? '?') . ' → ' . $to['name'] . ($reason !== '' && $to['kind'] === 'lost' ? ' (' . $reason . ')' : ''));
        if ($to['kind'] === 'won') \Sysfera\Hooks::do('crm.deal.won', (int)$deal['id']);
        if ($to['kind'] === 'lost') \Sysfera\Hooks::do('crm.deal.lost', (int)$deal['id']);
        return true;
    };

    /** contacts visible to the user (scope own = created by me or shared) */
    $contactList = function (array $f = [], int $limit = 50, int $offset = 0) use ($db, $scopeSql): array {
        $w = ['1=1']; $a = [];
        if (!empty($f['q'])) { $l = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%'; $w[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?)'; array_push($a, $l, $l, $l, $l); }
        if (!empty($f['type']) && in_array($f['type'], ['person', 'company'], true)) { $w[] = 'c.type = ?'; $a[] = $f['type']; }
        [$sc, $sa] = $scopeSql('c.owner_id');
        $where = implode(' AND ', $w) . $sc;
        $total = (int)$db->val("SELECT COUNT(*) FROM {p}contacts c WHERE $where", [...$a, ...$sa]);
        $rows = $db->rows("SELECT c.*, (SELECT COUNT(*) FROM {p}crm_deals d WHERE d.contact_id = c.id) AS deals FROM {p}contacts c WHERE $where ORDER BY c.id DESC LIMIT " . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset), [...$a, ...$sa]);
        return [$rows, $total];
    };

    /* =============================== dashboard =============================== */
    $route('GET', '/', function () use ($db, $page, $sf, $dealRows, $stageMap, $uid, $now, $scopeSql, $pipelines) {
        $stages = $stageMap(); $deals = $dealRows([], 2000);
        $sum = Pipe::summary($deals, $stages);
        $month = date('Y-m-01 00:00:00');
        $wonMonth = 0.0; foreach ($deals as $d) if ($d['kind'] === 'won' && $d['closed_at'] && $d['closed_at'] >= $month) $wonMonth += (float)$d['amount'];
        $tasks = $db->rows('SELECT t.*, d.title AS deal_title, c.name AS contact_name FROM {p}crm_tasks t LEFT JOIN {p}crm_deals d ON d.id = t.deal_id LEFT JOIN {p}contacts c ON c.id = t.contact_id
            WHERE t.assignee_id = ? AND t.done_at IS NULL ORDER BY (t.due_at IS NULL), t.due_at LIMIT 8', [$uid()]);
        $feed = $db->rows('SELECT tl.*, u.name AS user_name, d.title AS deal_title FROM {p}crm_timeline tl LEFT JOIN {p}users u ON u.id = tl.user_id LEFT JOIN {p}crm_deals d ON d.id = tl.deal_id ORDER BY tl.id DESC LIMIT 10');
        $funnel = []; foreach ($pipelines() as $p) { $rows = []; foreach ($db->rows('SELECT * FROM {p}crm_stages WHERE pipeline_id = ? AND kind = ? ORDER BY sort', [$p['id'], 'open']) as $s) {
            $n = 0; $v = 0.0; foreach ($deals as $d) if ((int)$d['stage_id'] === (int)$s['id']) { $n++; $v += (float)$d['amount']; } $rows[] = $s + ['n' => $n, 'sum' => $v]; } $funnel[] = ['p' => $p, 'rows' => $rows]; }
        $page('dashboard', ['sum' => $sum, 'wonMonth' => $wonMonth, 'tasks' => $tasks, 'feed' => $feed, 'funnel' => $funnel, 'recent' => array_slice($deals, 0, 6), 'nowTs' => time()], $sf->t('menu'), 'dashboard');
    });

    /* =============================== deals =============================== */
    $route('GET', '/deals', function () use ($page, $sf, $pipelines, $stagesOf, $dealRows, $users, $stageMap, $profile, $fieldDefs, $currency, $int) {
        $pl = $pipelines(); if (!$pl) { flash($sf->t('e_no_pipeline'), 'bad'); redirect('/p/crm/'); }
        $pid = $int($_GET['pipeline'] ?? '') ?? (int)$pl[0]['id'];
        if (!in_array($pid, array_map(fn($p) => (int)$p['id'], $pl), true)) $pid = (int)$pl[0]['id'];
        $f = ['pipeline' => $pid, 'q' => trim((string)($_GET['q'] ?? '')), 'owner' => $int($_GET['owner'] ?? ''), 'tag' => trim((string)($_GET['tag'] ?? '')),
              'status' => in_array($_GET['status'] ?? '', Pipe::KINDS, true) ? $_GET['status'] : ''];
        $view = ($_GET['view'] ?? 'board') === 'list' ? 'list' : 'board';
        $stages = $stagesOf($pid); $deals = $dealRows($f);
        $page('deals', ['pipelines' => $pl, 'pid' => $pid, 'stages' => $stages, 'deals' => $deals, 'board' => Pipe::board($deals, array_map(fn($s) => (int)$s['id'], $stages)), 'f' => $f, 'view' => $view,
            'users' => $users(), 'prof' => $profile(), 'sum' => Pipe::summary($deals, $stageMap()), 'defs' => Fields::applicable($fieldDefs(), 'deal', $pid), 'cur' => $currency(), 'sortBy' => (string)($_GET['sort'] ?? '')], $sf->t('deals'), 'deals');
    });

    $route('GET', '/deal/{id}', function (array $p) use ($page, $sf, $getDeal, $db, $fieldDefs, $stagesOf, $users, $dec, $profile, $reasons, $currency, $pipelines) {
        $d = $getDeal((int)$p['id']); if (!$d) { http_response_code(404); flash($sf->t('e_not_found'), 'bad'); redirect('/p/crm/deals'); }
        $d['extra'] = $dec($d['extra']);
        $page('deal', ['d' => $d, 'contact' => $d['contact_id'] ? $db->row('SELECT * FROM {p}contacts WHERE id = ?', [$d['contact_id']]) : null, 'stages' => $stagesOf((int)$d['pipeline_id']),
            'defs' => Fields::applicable($fieldDefs(), 'deal', (int)$d['pipeline_id']), 'users' => $users(), 'prof' => $profile(), 'reasons' => $reasons(), 'cur' => $currency(),
            'tasks' => $db->rows('SELECT t.*, u.name AS assignee_name FROM {p}crm_tasks t LEFT JOIN {p}users u ON u.id = t.assignee_id WHERE t.deal_id = ? ORDER BY (t.done_at IS NOT NULL), t.due_at', [$d['id']]),
            'timeline' => $db->rows('SELECT tl.*, u.name AS user_name FROM {p}crm_timeline tl LEFT JOIN {p}users u ON u.id = tl.user_id WHERE tl.deal_id = ? ORDER BY tl.id DESC LIMIT 100', [$d['id']]),
            'pipeline' => $db->row('SELECT * FROM {p}crm_pipelines WHERE id = ?', [$d['pipeline_id']]), 'nowTs' => time(), 'pipelines' => $pipelines()], $d['title'], 'deals');
    });

    $route('POST', '/deal/save', function () use ($db, $sf, $need, $int, $txt, $json, $dec, $now, $uid, $profile, $getDeal, $setStage, $log, $stagesOf, $fieldDefs, $self, $currency, $contactList) {
        $id = $int($_POST['id'] ?? '');
        $need($id ? 'edit' : 'create');
        $pid = $int($_POST['pipeline_id'] ?? ''); $sid = $int($_POST['stage_id'] ?? '');
        $old = $id ? $getDeal($id) : null;
        if ($id && !$old) { flash($sf->t('e_not_found'), 'bad'); redirect($self('deals')); }
        if ($old) $pid = (int)$old['pipeline_id'];
        $stages = $pid ? $stagesOf($pid) : [];
        $stageIds = array_map(fn($s) => (int)$s['id'], $stages);
        $title = $txt('title');
        if ($title === '' || !$pid || !$stages) { flash($sf->t('e_title'), 'bad'); redirect($self('deals')); }
        if (!$sid || !in_array($sid, $stageIds, true)) $sid = $old ? (int)$old['stage_id'] : (int)$stages[0]['id'];
        [$vals, $err] = Fields::clean(Fields::applicable($fieldDefs(), 'deal', $pid), (array)($_POST['f'] ?? []));
        if ($err) { flash($sf->t('e_field') . ' ' . implode('; ', $err), 'bad'); redirect($id ? $self("deal/$id") : $self('deals?pipeline=' . $pid)); }
        $contact = $int($_POST['contact_id'] ?? '');
        if (!$contact && $txt('new_contact') !== '') $contact = $sf->contacts()->create(['name' => $txt('new_contact'), 'type' => 'person', 'source' => 'crm', 'owner_id' => $uid() ?: null, 'custom' => []]);
        $owner = $int($_POST['owner_id'] ?? '');
        if (!$profile()['reassign'] || !$owner) $owner = $old ? ($old['owner_id'] === null ? $uid() : (int)$old['owner_id']) : $uid();
        $amount = round((float)str_replace([',', ' '], ['.', ''], (string)($_POST['amount'] ?? '0')), 2);
        $amount = max(0, min(9999999999999.0, $amount));
        $close = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['expected_close'] ?? '')) ? $_POST['expected_close'] : null;
        $cur = preg_match('/^[A-Z]{3}$/', strtoupper($txt('currency', 3))) ? strtoupper($txt('currency', 3)) : $currency();
        $tags = implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($t) => mb_substr(trim($t), 0, 30), explode(',', $txt('tags', 400)))))), 0, 12));
        if ($old) {
            $db->q('UPDATE {p}crm_deals SET title=?, contact_id=?, amount=?, currency=?, owner_id=?, expected_close=?, tags=?, extra=?, updated_at=? WHERE id=?',
                [$title, $contact, $amount, $cur, $owner, $close, $tags, $json($vals), $now(), $id]);
            if ((float)$old['amount'] !== $amount) $log($id, $contact, 'edit', $old['amount'] . ' → ' . $amount);
            if ($sid !== (int)$old['stage_id']) $setStage($old, $sid, $txt('reason'));
            flash($sf->t('saved'));
        } else {
            $db->q('INSERT INTO {p}crm_deals (pipeline_id, stage_id, title, contact_id, amount, currency, owner_id, expected_close, tags, extra, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$pid, $sid, $title, $contact, $amount, $cur, $owner, $close, $tags, $json($vals), $now()]);
            $id = $db->insertId(); $log($id, $contact, 'created', $title);
            \Sysfera\Hooks::do('crm.deal.created', $id);
            flash($sf->t('deal_created'));
        }
        redirect($self("deal/$id"));
    });

    /** drag & drop on the board (called with fetch, answers JSON) */
    $route('POST', '/deal/move', function () use ($getDeal, $setStage, $profile, $int, $txt, $sf) {
        header('Content-Type: application/json; charset=utf-8');
        $d = $getDeal((int)$int($_POST['id'] ?? '')); $to = (int)$int($_POST['stage'] ?? '');
        if (!$profile()['edit'] || !$d) { http_response_code(403); echo json_encode(['ok' => false, 'error' => $sf->t('e_denied')]); return; }
        echo json_encode(['ok' => $setStage($d, $to, $txt('reason'))]);
    });

    $route('POST', '/deal/{id}/note', function (array $p) use ($getDeal, $txt, $log, $self, $sf, $need, $db, $now) {
        $need('edit'); $d = $getDeal((int)$p['id']); $b = $txt('body', 3000);
        if ($d && $b !== '') { $log((int)$d['id'], $d['contact_id'] ? (int)$d['contact_id'] : null, 'note', $b); $db->q('UPDATE {p}crm_deals SET updated_at = ? WHERE id = ?', [$now(), $d['id']]); }
        redirect($self('deal/' . (int)$p['id']));
    });

    $route('POST', '/deal/{id}/delete', function (array $p) use ($db, $getDeal, $need, $sf, $self) {
        $need('delete'); $d = $getDeal((int)$p['id']);
        if ($d) { $db->q('DELETE FROM {p}crm_tasks WHERE deal_id = ?', [$d['id']]); $db->q('DELETE FROM {p}crm_timeline WHERE deal_id = ?', [$d['id']]); $db->q('DELETE FROM {p}crm_deals WHERE id = ?', [$d['id']]); flash($sf->t('deleted')); }
        redirect($self('deals'));
    });

    /* =============================== contacts =============================== */
    $route('GET', '/contacts', function () use ($page, $sf, $contactList, $profile) {
        $per = 50; $pg = max(1, (int)($_GET['page'] ?? 1));
        $f = ['q' => trim((string)($_GET['q'] ?? '')), 'type' => (string)($_GET['type'] ?? '')];
        [$rows, $total] = $contactList($f, $per, ($pg - 1) * $per);
        $page('contacts', ['rows' => $rows, 'total' => $total, 'pg' => $pg, 'pages' => max(1, (int)ceil($total / $per)), 'f' => $f, 'prof' => $profile()], $sf->t('contacts'), 'contacts');
    });

    $route('GET', '/contact/{id}', function (array $p) use ($page, $sf, $db, $profile, $uid, $fieldDefs, $dealRows) {
        $c = $sf->contacts()->find((int)$p['id']);
        if (!$c || !Pipe::visible($profile(), $c['owner_id'] === null ? null : (int)$c['owner_id'], $uid())) { flash($sf->t('e_not_found'), 'bad'); redirect('/p/crm/contacts'); }
        $page('contact', ['c' => $c, 'crm' => (array)($c['custom']['crm'] ?? []), 'defs' => Fields::applicable($fieldDefs(), 'contact'), 'deals' => $dealRows(['contact' => (int)$c['id']], 100), 'prof' => $profile(),
            'tasks' => $db->rows('SELECT t.* FROM {p}crm_tasks t WHERE t.contact_id = ? ORDER BY (t.done_at IS NOT NULL), t.due_at', [$c['id']]),
            'timeline' => $db->rows('SELECT tl.*, u.name AS user_name FROM {p}crm_timeline tl LEFT JOIN {p}users u ON u.id = tl.user_id WHERE tl.contact_id = ? ORDER BY tl.id DESC LIMIT 100', [$c['id']]), 'nowTs' => time()], $c['name'], 'contacts');
    });

    $route('POST', '/contact/save', function () use ($sf, $need, $int, $txt, $profile, $uid, $fieldDefs, $self, $log, $db) {
        $id = $int($_POST['id'] ?? ''); $need($id ? 'edit' : 'create');
        $name = $txt('name');
        $old = $id ? $sf->contacts()->find($id) : null;
        if ($id && (!$old || !Pipe::visible($profile(), $old['owner_id'] === null ? null : (int)$old['owner_id'], $uid()))) { flash($sf->t('e_not_found'), 'bad'); redirect($self('contacts')); }
        if ($name === '') { flash($sf->t('e_name'), 'bad'); redirect($id ? $self("contact/$id") : $self('contacts')); }
        $email = mb_strtolower($txt('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash($sf->t('e_email'), 'bad'); redirect($id ? $self("contact/$id") : $self('contacts')); }
        [$vals, $err] = Fields::clean(Fields::applicable($fieldDefs(), 'contact'), (array)($_POST['f'] ?? []));
        if ($err) { flash($sf->t('e_field') . ' ' . implode('; ', $err), 'bad'); redirect($id ? $self("contact/$id") : $self('contacts')); }
        $custom = (array)($old['custom'] ?? []); $custom['crm'] = $vals;
        $data = ['name' => $name, 'type' => ($_POST['type'] ?? '') === 'company' ? 'company' : 'person', 'email' => $email ?: null, 'phone' => $txt('phone', 60) ?: null,
                 'company' => $txt('company') ?: null, 'address' => $txt('address', 1000) ?: null, 'notes' => $txt('notes', 5000) ?: null, 'custom' => $custom];
        if ($old) { $sf->contacts()->update($id, $data); flash($sf->t('saved')); redirect($self("contact/$id")); }
        if ($email !== '' && $db->val('SELECT id FROM {p}contacts WHERE email = ? LIMIT 1', [$email])) { flash($sf->t('e_dupe'), 'warn'); }
        $id = $sf->contacts()->create($data + ['source' => 'crm', 'owner_id' => $uid() ?: null]);
        $log(null, $id, 'created', $name); flash($sf->t('contact_created')); redirect($self("contact/$id"));
    });

    $route('POST', '/contact/{id}/delete', function (array $p) use ($sf, $need, $db, $self, $profile, $uid) {
        $need('delete'); $c = $sf->contacts()->find((int)$p['id']);
        if ($c && Pipe::visible($profile(), $c['owner_id'] === null ? null : (int)$c['owner_id'], $uid())) {
            $db->q('UPDATE {p}crm_deals SET contact_id = NULL WHERE contact_id = ?', [$c['id']]);
            $db->q('UPDATE {p}crm_tasks SET contact_id = NULL WHERE contact_id = ?', [$c['id']]);
            $db->q('DELETE FROM {p}crm_timeline WHERE contact_id = ? AND deal_id IS NULL', [$c['id']]);
            $sf->contacts()->delete((int)$c['id']); flash($sf->t('deleted'));
        }
        redirect($self('contacts'));
    });

    /** live search for the deal form */
    $route('GET', '/contacts/find', function () use ($contactList) {
        header('Content-Type: application/json; charset=utf-8');
        [$rows] = $contactList(['q' => trim((string)($_GET['q'] ?? ''))], 10);
        echo json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'company' => $r['company'], 'email' => $r['email']], $rows), JSON_UNESCAPED_UNICODE);
    });

    /* =============================== tasks =============================== */
    $route('GET', '/tasks', function () use ($page, $sf, $db, $uid, $profile, $users, $int) {
        $show = in_array($_GET['show'] ?? '', ['mine', 'all', 'done'], true) ? $_GET['show'] : 'mine';
        if ($show === 'all' && $profile()['scope'] !== 'all') $show = 'mine';
        $w = ['1=1']; $a = [];
        if ($show === 'mine') { $w[] = 't.assignee_id = ?'; $a[] = $uid(); $w[] = 't.done_at IS NULL'; }
        elseif ($show === 'done') { $w[] = 't.done_at IS NOT NULL'; if ($profile()['scope'] !== 'all') { $w[] = 't.assignee_id = ?'; $a[] = $uid(); } }
        else $w[] = 't.done_at IS NULL';
        if ($as = $int($_GET['assignee'] ?? '') and $profile()['scope'] === 'all') { $w[] = 't.assignee_id = ?'; $a[] = $as; }
        $rows = $db->rows('SELECT t.*, u.name AS assignee_name, d.title AS deal_title, c.name AS contact_name FROM {p}crm_tasks t LEFT JOIN {p}users u ON u.id = t.assignee_id LEFT JOIN {p}crm_deals d ON d.id = t.deal_id
            LEFT JOIN {p}contacts c ON c.id = t.contact_id WHERE ' . implode(' AND ', $w) . ' ORDER BY (t.due_at IS NULL), t.due_at, t.id DESC LIMIT 300', $a);
        $page('tasks', ['rows' => $rows, 'show' => $show, 'users' => $users(), 'prof' => $profile(), 'nowTs' => time(), 'assignee' => $as ?? 0], $sf->t('tasks'), 'tasks');
    });

    $route('POST', '/task/save', function () use ($db, $sf, $need, $int, $txt, $uid, $profile, $log, $self, $back, $getDeal) {
        $need('create');
        $title = $txt('title'); $type = in_array($_POST['type'] ?? '', Pipe::TASK_TYPES, true) ? $_POST['type'] : 'todo';
        $due = null; $d = (string)($_POST['due_date'] ?? ''); $tm = (string)($_POST['due_time'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $due = $d . ' ' . (preg_match('/^\d{2}:\d{2}$/', $tm) ? $tm . ':00' : '00:00:00');
        $deal = $int($_POST['deal_id'] ?? ''); $contact = $int($_POST['contact_id'] ?? '');
        if ($deal && !$getDeal($deal)) $deal = null;
        $as = $int($_POST['assignee_id'] ?? '');
        if (!$as || !$profile()['reassign']) $as = $uid();
        if ($title === '') { flash($sf->t('e_title'), 'bad'); $back($self('tasks')); }
        $db->q('INSERT INTO {p}crm_tasks (deal_id, contact_id, type, title, notes, due_at, assignee_id, created_by) VALUES (?,?,?,?,?,?,?,?)', [$deal, $contact, $type, $title, $txt('notes', 3000) ?: null, $due, $as, $uid()]);
        if ($deal || $contact) $log($deal, $contact, 'task', $sf->t('tt_' . $type) . ': ' . $title);
        flash($sf->t('task_created')); $back($self('tasks'));
    });

    $route('POST', '/task/{id}/toggle', function (array $p) use ($db, $uid, $profile, $now, $log, $self, $back, $sf) {
        $t = $db->row('SELECT * FROM {p}crm_tasks WHERE id = ?', [(int)$p['id']]);
        if ($t && ($profile()['scope'] === 'all' || (int)$t['assignee_id'] === $uid()) && $profile()['edit']) {
            $done = $t['done_at'] === null; $db->q('UPDATE {p}crm_tasks SET done_at = ? WHERE id = ?', [$done ? $now() : null, $t['id']]);
            if ($done && ($t['deal_id'] || $t['contact_id'])) $log($t['deal_id'] ? (int)$t['deal_id'] : null, $t['contact_id'] ? (int)$t['contact_id'] : null, 'task_done', $t['title']);
        }
        $back($self('tasks'));
    });

    $route('POST', '/task/{id}/delete', function (array $p) use ($db, $need, $uid, $profile, $self, $back) {
        $need('delete'); $t = $db->row('SELECT * FROM {p}crm_tasks WHERE id = ?', [(int)$p['id']]);
        if ($t && ($profile()['scope'] === 'all' || (int)$t['assignee_id'] === $uid())) $db->q('DELETE FROM {p}crm_tasks WHERE id = ?', [$t['id']]);
        $back($self('tasks'));
    });

    /* =============================== reports =============================== */
    $route('GET', '/reports', function () use ($page, $sf, $db, $pipelines, $stagesOf, $dealRows, $stageMap, $int, $scopeSql) {
        $pl = $pipelines(); if (!$pl) redirect('/p/crm/');
        $pid = $int($_GET['pipeline'] ?? '') ?? (int)$pl[0]['id'];
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
        $deals = $dealRows(['pipeline' => $pid], 2000); $stages = $stageMap();
        $sum = Pipe::summary($deals, $stages);
        $perStage = []; foreach ($stagesOf($pid) as $s) { $n = 0; $v = 0.0; foreach ($deals as $d) if ((int)$d['stage_id'] === (int)$s['id']) { $n++; $v += (float)$d['amount']; } $perStage[] = $s + ['n' => $n, 'sum' => $v]; }
        $closed = array_values(array_filter($deals, fn($d) => $d['closed_at'] && substr($d['closed_at'], 0, 10) >= $from && substr($d['closed_at'], 0, 10) <= $to));
        $byOwner = []; $reasons = []; $byMonth = [];
        foreach ($closed as $d) {
            $o = $d['owner_name'] ?: '—'; $byOwner[$o] ??= ['won_n' => 0, 'won_sum' => 0.0, 'lost_n' => 0];
            if ($d['kind'] === 'won') { $byOwner[$o]['won_n']++; $byOwner[$o]['won_sum'] += (float)$d['amount']; $m = substr($d['closed_at'], 0, 7); $byMonth[$m] = ($byMonth[$m] ?? 0) + (float)$d['amount']; }
            else { $byOwner[$o]['lost_n']++; $r = $d['lost_reason'] ?: '—'; $reasons[$r] = ($reasons[$r] ?? 0) + 1; }
        }
        uasort($byOwner, fn($a, $b) => $b['won_sum'] <=> $a['won_sum']); arsort($reasons); ksort($byMonth);
        $page('reports', ['pl' => $pl, 'pid' => $pid, 'from' => $from, 'to' => $to, 'sum' => $sum, 'perStage' => $perStage, 'byOwner' => $byOwner, 'reasons' => $reasons, 'byMonth' => $byMonth,
            'closedSum' => Pipe::summary($closed, $stages)], $sf->t('reports'), 'reports');
    });

    /* =============================== export / import =============================== */
    $route('GET', '/export/{what}', function (array $p) use ($sf, $need, $contactList, $dealRows, $db, $fieldDefs, $dec, $scopeSql) {
        $need('export'); $what = $p['what'];
        if (!in_array($what, ['contacts', 'deals', 'tasks'], true)) { http_response_code(404); return; }
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="crm-' . $what . '-' . date('Ymd') . '.csv"'); header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        if ($what === 'contacts') {
            $defs = Fields::applicable($fieldDefs(), 'contact'); [$rows] = $contactList([], 200, 0);
            $all = []; $off = 0; do { [$rows] = $contactList([], 200, $off); $all = array_merge($all, $rows); $off += 200; } while (count($rows) === 200 && $off < 10000);
            echo Pipe::csvLine(array_merge(['id', 'name', 'type', 'email', 'phone', 'company', 'address', 'notes'], array_column($defs, 'label'))) . "\r\n";
            foreach ($all as $c) { $cu = $dec($c['custom'])['crm'] ?? []; echo Pipe::csvLine(array_merge([$c['id'], $c['name'], $c['type'], $c['email'], $c['phone'], $c['company'], $c['address'], $c['notes']], array_map(fn($d) => Fields::show($d, $cu[$d['code']] ?? null), $defs))) . "\r\n"; }
        } elseif ($what === 'deals') {
            echo Pipe::csvLine(['id', 'title', 'pipeline', 'stage', 'status', 'amount', 'currency', 'contact', 'owner', 'expected_close', 'tags', 'lost_reason', 'created']) . "\r\n";
            $pn = []; foreach ($db->rows('SELECT id, name FROM {p}crm_pipelines') as $r) $pn[$r['id']] = $r['name'];
            foreach ($dealRows([], 2000) as $d) echo Pipe::csvLine([$d['id'], $d['title'], $pn[$d['pipeline_id']] ?? '', $d['stage_name'], $d['kind'], $d['amount'], $d['currency'], $d['contact_name'], $d['owner_name'], $d['expected_close'], $d['tags'], $d['lost_reason'], $d['created_at']]) . "\r\n";
        } else {
            echo Pipe::csvLine(['id', 'type', 'title', 'due', 'done', 'assignee', 'deal', 'contact']) . "\r\n";
            foreach ($db->rows('SELECT t.*, u.name AS an, d.title AS dt, c.name AS cn FROM {p}crm_tasks t LEFT JOIN {p}users u ON u.id = t.assignee_id LEFT JOIN {p}crm_deals d ON d.id = t.deal_id LEFT JOIN {p}contacts c ON c.id = t.contact_id ORDER BY t.id DESC LIMIT 5000') as $t)
                echo Pipe::csvLine([$t['id'], $t['type'], $t['title'], $t['due_at'], $t['done_at'], $t['an'], $t['dt'], $t['cn']]) . "\r\n";
        }
        exit;
    });

    $route('*', '/import', function () use ($sf, $page, $need, $db, $uid, $now, $pipelines, $stagesOf, $log, $self, $profile) {
        $need('import'); $res = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $f = $_FILES['csv'] ?? null;
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2 * 1024 * 1024) { flash($sf->t('e_file'), 'bad'); redirect($self('import')); }
            $rows = Pipe::csvParse((string)file_get_contents($f['tmp_name']));
            if (count($rows) < 2) { flash($sf->t('e_file'), 'bad'); redirect($self('import')); }
            $rows = array_slice($rows, 0, 2001);
            $head = array_map(fn($h) => mb_strtolower(trim((string)$h)), array_shift($rows));
            $syn = ['name' => ['name', 'имя', 'название', 'фио', 'клиент', 'title', 'заголовок', 'сделка'], 'email' => ['email', 'e-mail', 'почта', 'e-mail'], 'phone' => ['phone', 'телефон', 'тел'], 'company' => ['company', 'компания', 'организация'],
                    'notes' => ['notes', 'заметки', 'комментарий', 'примечание'], 'amount' => ['amount', 'сумма', 'бюджет'], 'contact' => ['contact', 'контакт']];
            $col = []; foreach ($syn as $k => $names) foreach ($head as $i => $h) if (in_array($h, $names, true) && !isset($col[$k])) $col[$k] = $i;
            $get = fn(array $r, string $k) => isset($col[$k]) ? trim((string)($r[$col[$k]] ?? '')) : '';
            $kind = ($_POST['kind'] ?? '') === 'deals' ? 'deals' : 'contacts';
            $ok = 0; $skip = 0;
            if (!isset($col['name'])) { flash($sf->t('e_no_name_col'), 'bad'); redirect($self('import')); }
            if ($kind === 'contacts') {
                foreach ($rows as $r) {
                    $name = mb_substr($get($r, 'name'), 0, 190); $email = mb_strtolower($get($r, 'email'));
                    if ($name === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) { $skip++; continue; }
                    if ($email !== '' && $db->val('SELECT id FROM {p}contacts WHERE email = ? LIMIT 1', [$email])) { $skip++; continue; }
                    $sf->contacts()->create(['name' => $name, 'type' => 'person', 'email' => $email ?: null, 'phone' => mb_substr($get($r, 'phone'), 0, 60) ?: null, 'company' => mb_substr($get($r, 'company'), 0, 190) ?: null,
                        'notes' => mb_substr($get($r, 'notes'), 0, 5000) ?: null, 'source' => 'crm-import', 'owner_id' => $uid() ?: null, 'custom' => []]); $ok++;
                }
            } else {
                $pid = (int)($_POST['pipeline_id'] ?? 0); $st = $stagesOf($pid);
                if (!$st) { flash($sf->t('e_no_pipeline'), 'bad'); redirect($self('import')); }
                foreach ($rows as $r) {
                    $title = mb_substr($get($r, 'name'), 0, 190); if ($title === '') { $skip++; continue; }
                    $amount = max(0, round((float)str_replace([',', ' '], ['.', ''], $get($r, 'amount')), 2));
                    $cid = null; $cn = $get($r, 'contact'); if ($cn !== '') { $cid = $db->val('SELECT id FROM {p}contacts WHERE name = ? LIMIT 1', [$cn]); if (!$cid) $cid = $sf->contacts()->create(['name' => mb_substr($cn, 0, 190), 'type' => 'person', 'source' => 'crm-import', 'owner_id' => $uid() ?: null, 'custom' => []]); }
                    $db->q('INSERT INTO {p}crm_deals (pipeline_id, stage_id, title, contact_id, amount, currency, owner_id, updated_at) VALUES (?,?,?,?,?,?,?,?)', [$pid, $st[0]['id'], $title, $cid ?: null, $amount, $sf->setting('currency', 'RUB') ?: 'RUB', $uid() ?: null, $now()]);
                    $log($db->insertId(), $cid ? (int)$cid : null, 'created', $title); $ok++;
                }
            }
            $res = ['ok' => $ok, 'skip' => $skip];
        }
        $page('import', ['res' => $res, 'pipelines' => $pipelines()], $sf->t('import'), 'import');
    });

    /* =============================== settings =============================== */
    $route('GET', '/settings', function () use ($page, $sf, $db, $profile, $fieldDefs, $users, $reasons, $currency, $self) {
        if (!$profile()['settings']) { flash($sf->t('e_denied'), 'bad'); redirect($self()); }
        $pl = $db->rows('SELECT * FROM {p}crm_pipelines ORDER BY sort, id');
        foreach ($pl as &$p) { $p['stages'] = $db->rows('SELECT s.*, (SELECT COUNT(*) FROM {p}crm_deals d WHERE d.stage_id = s.id) AS deals FROM {p}crm_stages s WHERE s.pipeline_id = ? ORDER BY s.sort, s.id', [$p['id']]); $p['deals'] = (int)$db->val('SELECT COUNT(*) FROM {p}crm_deals WHERE pipeline_id = ?', [$p['id']]); } unset($p);
        $profs = array_map(function ($r) { $r['p'] = Pipe::profile(json_decode((string)$r['perms'], true) ?: []); return $r; }, $db->rows('SELECT * FROM {p}crm_profiles ORDER BY id'));
        $assign = []; foreach ($db->rows('SELECT user_id, profile_id FROM {p}crm_user_profile') as $r) $assign[(int)$r['user_id']] = (int)$r['profile_id'];
        $page('settings', ['pl' => $pl, 'defs' => $fieldDefs(), 'profs' => $profs, 'assign' => $assign, 'users' => $db->rows("SELECT id, name, email, role FROM {p}users WHERE status = 'active' ORDER BY name"), 'reasons' => $reasons(),
            'cur' => $currency(), 'defProf' => (int)$sf->setting('default_profile', '0')], $sf->t('settings'), 'settings');
    });

    $route('POST', '/settings/{do}', function (array $p) use ($sf, $db, $profile, $int, $txt, $color, $json, $self, $fieldDefs) {
        if (!$profile()['settings']) { flash($sf->t('e_denied'), 'bad'); redirect($self()); }
        $do = $p['do']; $ok = true;
        switch ($do) {
            case 'pipeline':
                $id = $int($_POST['id'] ?? ''); $name = $txt('name', 120);
                if ($name === '') { $ok = false; break; }
                if ($id) $db->q('UPDATE {p}crm_pipelines SET name = ?, color = ? WHERE id = ?', [$name, $color($_POST['color'] ?? '', '#4f46e5'), $id]);
                else { $db->q('INSERT INTO {p}crm_pipelines (name, color, sort) VALUES (?,?,?)', [$name, $color($_POST['color'] ?? '', '#4f46e5'), Pipe::nextSort($db->rows('SELECT sort FROM {p}crm_pipelines'))]);
                    $pid = $db->insertId(); foreach ([['st_new', 10, 'open', '#94a3b8'], ['st_progress', 50, 'open', '#60a5fa'], ['st_won', 100, 'won', '#16a34a'], ['st_lost', 0, 'lost', '#dc2626']] as $i => [$k, $pr, $kd, $cl])
                        $db->q('INSERT INTO {p}crm_stages (pipeline_id, name, prob, kind, color, sort) VALUES (?,?,?,?,?,?)', [$pid, $sf->t($k), $pr, $kd, $cl, ($i + 1) * 10]); }
                break;
            case 'pipeline_delete':
                $id = $int($_POST['id'] ?? '');
                if (!$id || (int)$db->val('SELECT COUNT(*) FROM {p}crm_deals WHERE pipeline_id = ?', [$id]) > 0 || (int)$db->val('SELECT COUNT(*) FROM {p}crm_pipelines') < 2) { flash($sf->t('e_pipe_used'), 'bad'); redirect($self('settings')); }
                $db->q('DELETE FROM {p}crm_stages WHERE pipeline_id = ?', [$id]); $db->q('DELETE FROM {p}crm_fields WHERE pipeline_id = ?', [$id]); $db->q('DELETE FROM {p}crm_pipelines WHERE id = ?', [$id]); break;
            case 'stage':
                $id = $int($_POST['id'] ?? ''); $pid = $int($_POST['pipeline_id'] ?? ''); $name = $txt('name', 120);
                $kind = in_array($_POST['kind'] ?? '', Pipe::KINDS, true) ? $_POST['kind'] : 'open';
                $prob = $kind === 'won' ? 100 : ($kind === 'lost' ? 0 : max(0, min(100, (int)($_POST['prob'] ?? 0))));
                if ($name === '') { $ok = false; break; }
                if ($id) $db->q('UPDATE {p}crm_stages SET name = ?, prob = ?, kind = ?, color = ?, sort = ? WHERE id = ?', [$name, $prob, $kind, $color($_POST['color'] ?? ''), (int)($_POST['sort'] ?? 0), $id]);
                elseif ($pid) $db->q('INSERT INTO {p}crm_stages (pipeline_id, name, prob, kind, color, sort) VALUES (?,?,?,?,?,?)', [$pid, $name, $prob, $kind, $color($_POST['color'] ?? ''), Pipe::nextSort($db->rows('SELECT sort FROM {p}crm_stages WHERE pipeline_id = ?', [$pid]))]);
                break;
            case 'stage_delete':
                $id = $int($_POST['id'] ?? '');
                if (!$id || (int)$db->val('SELECT COUNT(*) FROM {p}crm_deals WHERE stage_id = ?', [$id]) > 0) { flash($sf->t('e_stage_used'), 'bad'); redirect($self('settings')); }
                $db->q('DELETE FROM {p}crm_stages WHERE id = ?', [$id]); break;
            case 'field':
                $id = $int($_POST['id'] ?? ''); $label = $txt('label', 120);
                $entity = in_array($_POST['entity'] ?? '', Fields::ENTITIES, true) ? $_POST['entity'] : 'deal';
                $type = in_array($_POST['type'] ?? '', Fields::TYPES, true) ? $_POST['type'] : 'text';
                $pipe = $entity === 'deal' ? $int($_POST['pipeline_id'] ?? '') : null;
                $opts = in_array($type, ['select', 'multiselect'], true) ? Fields::options((string)($_POST['options'] ?? '')) : [];
                if ($label === '' || (in_array($type, ['select', 'multiselect'], true) && !$opts)) { flash($sf->t('e_field_def'), 'bad'); redirect($self('settings') . '#fields'); }
                if ($id) $db->q('UPDATE {p}crm_fields SET label = ?, type = ?, options = ?, required = ?, pipeline_id = ? WHERE id = ?', [$label, $type, $json($opts), empty($_POST['required']) ? 0 : 1, $pipe, $id]);
                else { $taken = array_column($fieldDefs(), 'code'); $db->q('INSERT INTO {p}crm_fields (entity, pipeline_id, code, label, type, options, required, sort) VALUES (?,?,?,?,?,?,?,?)',
                    [$entity, $pipe, Fields::code($label, $taken), $label, $type, $json($opts), empty($_POST['required']) ? 0 : 1, Pipe::nextSort($db->rows('SELECT sort FROM {p}crm_fields'))]); }
                flash($sf->t('saved')); redirect($self('settings') . '#fields');
            case 'field_delete':
                if ($id = $int($_POST['id'] ?? '')) $db->q('DELETE FROM {p}crm_fields WHERE id = ?', [$id]);
                flash($sf->t('saved')); redirect($self('settings') . '#fields');
            case 'profile':
                $id = $int($_POST['id'] ?? ''); $name = $txt('name', 120); if ($name === '') { $ok = false; break; }
                $perms = Pipe::profile((array)($_POST['perm'] ?? []) + ['scope' => $_POST['scope'] ?? 'own']);
                if ($id) $db->q('UPDATE {p}crm_profiles SET name = ?, perms = ? WHERE id = ?', [$name, $json($perms), $id]);
                else $db->q('INSERT INTO {p}crm_profiles (name, perms) VALUES (?,?)', [$name, $json($perms)]);
                flash($sf->t('saved')); redirect($self('settings') . '#profiles');
            case 'profile_delete':
                $id = $int($_POST['id'] ?? '');
                if ($id && $id !== (int)$sf->setting('default_profile', '0') && !(int)$db->val('SELECT COUNT(*) FROM {p}crm_user_profile WHERE profile_id = ?', [$id])) $db->q('DELETE FROM {p}crm_profiles WHERE id = ?', [$id]);
                else flash($sf->t('e_profile_used'), 'bad');
                redirect($self('settings') . '#profiles');
            case 'assign':
                $valid = array_map('intval', array_column($db->rows('SELECT id FROM {p}crm_profiles'), 'id'));
                foreach ((array)($_POST['u'] ?? []) as $u => $pid) { $u = (int)$u; $pid = (int)$pid;
                    if ($u > 0 && in_array($pid, $valid, true)) $db->q('REPLACE INTO {p}crm_user_profile (user_id, profile_id) VALUES (?,?)', [$u, $pid]); }
                if (in_array((int)($_POST['default_profile'] ?? 0), $valid, true)) $sf->setSetting('default_profile', (string)(int)$_POST['default_profile']);
                flash($sf->t('saved')); redirect($self('settings') . '#profiles');
            case 'general':
                $sf->setSetting('reasons', $json(Fields::options((string)($_POST['reasons'] ?? ''))));
                $c = strtoupper($txt('currency', 3)); if (preg_match('/^[A-Z]{3}$/', $c)) $sf->setSetting('currency', $c);
                break;
            default: http_response_code(404); return;
        }
        flash($sf->t($ok ? 'saved' : 'e_name'), $ok ? 'ok' : 'bad');
        redirect($self('settings'));
    });

    /* ---------- dashboard widget ---------- */
    \Sysfera\Hooks::on('dashboard.widgets', function (array $w) use ($sf, $db, $uid, $self) {
        if (!$sf->can('crm.use') || !$uid()) return $w;
        try {
            $rows = $db->rows('SELECT due_at FROM {p}crm_tasks WHERE assignee_id = ? AND done_at IS NULL AND due_at IS NOT NULL', [$uid()]);
        } catch (\Throwable $e) { return $w; }
        $over = 0; $today = 0; $t = time();
        foreach ($rows as $r) { $s = Pipe::taskState($r['due_at'], null, $t); if ($s === 'overdue') $over++; elseif ($s === 'today') $today++; }
        $w[] = '<div class="card"><div class="row between"><b>' . e($sf->t('menu')) . '</b><a href="' . e(url($self('tasks'))) . '">' . e($sf->t('tasks')) . ' →</a></div><p style="margin:6px 0 0">'
            . e($sf->t('w_today', ['n' => $today])) . ' · <span style="color:' . ($over ? 'var(--bad)' : 'inherit') . '">' . e($sf->t('w_overdue', ['n' => $over])) . '</span></p></div>';
        return $w;
    });
};
