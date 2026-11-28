<?php
declare(strict_types=1);

/** Sysfera Notes: personal notes with a title, tags, priority and search; edit, share by e-mail, send to another user of the site. */
return function (\Sysfera\Plugin $sf): void {
    $sf->menu('menu', '/', ['cap' => 'hello.use', 'order' => 30, 'icon' => 'note']);

    $me = fn(): int => (int)$sf->auth()->id();
    /** "a, #B  c" -> ",a,b,c," (max 8 tags of 24 characters) */
    $tags = function (string $raw): string {
        $out = [];
        foreach (preg_split('/[,;\s#]+/u', mb_strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            $t = mb_substr((string)preg_replace('/[^\p{L}\p{N}_-]+/u', '', $t), 0, 24);
            if ($t !== '' && !in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= 8) break;
        }
        return $out ? ',' . implode(',', $out) . ',' : '';
    };
    /** one theme per note: spaces collapsed, max 80 characters, empty = no theme */
    $theme = fn(string $raw): string => trim(mb_substr(trim((string)preg_replace('/\s+/u', ' ', $raw)), 0, 80));
    /** 1 = low, 2 = normal, 3 = high; anything else gives the default */
    $level = fn(mixed $raw, int $def = 2): int => is_scalar($raw) && in_array((string)$raw, ['1', '2', '3'], true) ? (int)$raw : $def;
    $mine = function (int $id) use ($sf, $me): ?array {   // a note the user owns (notes from before this version have no owner: everybody with access)
        $n = $sf->db()->row('SELECT * FROM {p}hello_notes WHERE id = ? AND (user_id = ? OR user_id IS NULL)', [$id, $me()]);
        return $n ?: null;
    };

    $sf->route('*', '/', function () use ($sf, $me, $tags, $theme, $level, $mine) {
        $db = $sf->db(); $uid = $me(); $user = $sf->auth()->user() ?? [];
        // the site deploy copies bundled components without running their migrations: apply pending ones once per version
        if ($sf->setting('schema') !== (string)$sf->manifest['version']) { (new \Sysfera\Migrator($db))->run($sf->dir . '/migrations', 'plugin:hello:'); $sf->setSetting('schema', (string)$sf->manifest['version']); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $do = (string)($_POST['do'] ?? 'save'); $id = (int)($_POST['id'] ?? 0); $back = '/p/hello/' . (str_starts_with((string)($_POST['qs'] ?? ''), '?') ? preg_replace('/[^\w\-=&%+.?]/u', '', (string)$_POST['qs']) : '');
            $n = $id ? $mine($id) : null;
            if ($do === 'save') {
                $title = trim(mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190)); $text = trim(mb_substr((string)($_POST['text'] ?? ''), 0, 5000));
                if ($title === '' && $text === '') { flash($sf->t('e_empty'), 'bad'); redirect($back); }
                if ($id && !$n) { flash($sf->t('e_missing'), 'bad'); redirect($back); }
                $p = $level($_POST['priority'] ?? null, $level($n['priority'] ?? null));
                if ($n) { $db->q('UPDATE {p}hello_notes SET title = ?, body = ?, tags = ?, theme = ?, priority = ?, updated_at = NOW() WHERE id = ?', [$title, $text, $tags((string)($_POST['tags'] ?? '')), $theme((string)($_POST['theme'] ?? '')), $p, $n['id']]); flash($sf->t('saved')); }
                else { $db->q('INSERT INTO {p}hello_notes (user_id, title, body, tags, theme, priority) VALUES (?,?,?,?,?,?)', [$uid, $title, $text, $tags((string)($_POST['tags'] ?? '')), $theme((string)($_POST['theme'] ?? '')), $p]); flash($sf->t('added')); }
            } elseif (!$n) { flash($sf->t('e_missing'), 'bad');
            } elseif ($do === 'priority') {
                $p = $level($_POST['priority'] ?? null, 0);
                if ($p) { $db->q('UPDATE {p}hello_notes SET priority = ? WHERE id = ?', [$p, $n['id']]); flash($sf->t('prio_saved')); }
            } elseif ($do === 'delete') { $db->q('DELETE FROM {p}hello_notes WHERE id = ?', [$n['id']]); flash($sf->t('deleted'));
            } elseif ($do === 'email') {
                $to = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)($_POST['to'] ?? '')) ?: []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
                sf_session(); $_SESSION['note_mail'] = array_filter((array)($_SESSION['note_mail'] ?? []), fn($t) => $t > time() - 3600);
                if (!$to || count($to) > 5) flash($sf->t('e_to'), 'bad');
                elseif (count($_SESSION['note_mail']) >= 20) flash($sf->t('e_rate'), 'bad');
                else {
                    $name = (string)($user['name'] ?? ''); $head = $n['title'] !== '' ? $n['title'] : mb_substr(trim((string)preg_split('/\R/', (string)$n['body'])[0]), 0, 80);
                    $text = $head . "\n\n" . $n['body'] . ($n['theme'] !== '' ? "\n\n" . $sf->t('f_theme') . ': ' . $n['theme'] : '') . "\n\n" . $sf->t('f_priority') . ': ' . $sf->t('prio_' . $level($n['priority'] ?? null)) . ($n['tags'] !== '' ? "\n\n#" . implode(' #', array_filter(explode(',', $n['tags']))) : '') . "\n\n— " . $name;
                    $err = $sf->mail($to, $head !== '' ? $head : $sf->t('title'), $text, null, [(string)($user['email'] ?? ''), $name]);   // "From" = the signed-in user
                    if ($err === null) { $_SESSION['note_mail'][] = time(); flash($sf->t('mailed', ['n' => count($to)])); }
                    else flash($err === 'not_configured' ? $sf->t('e_mail_off') : $sf->t('e_mail', ['e' => $err]), 'bad');
                }
            } elseif ($do === 'send') {
                $to = (int)($_POST['user'] ?? 0);
                $ok = $to && $to !== $uid && $db->val("SELECT 1 FROM {p}users WHERE id = ? AND status = 'active'", [$to]);
                if (!$ok) flash($sf->t('e_user'), 'bad');
                elseif ((int)$db->val('SELECT COUNT(*) FROM {p}hello_notes WHERE from_user_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$uid]) >= 50) flash($sf->t('e_rate'), 'bad');
                else {
                    $db->q('INSERT INTO {p}hello_notes (user_id, from_user_id, title, body, tags, theme, priority) VALUES (?,?,?,?,?,?,?)', [$to, $uid, $n['title'], $n['body'], $n['tags'], $n['theme'], $level($n['priority'] ?? null)]);
                    $sf->log('note.sent', ['to' => $to]); flash($sf->t('sent'));
                }
            }
            redirect($back);
        }

        /* list: search, tag and priority filters, sort */
        $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80)); $tag = mb_strtolower(trim((string)($_GET['tag'] ?? ''))); $sort = in_array($_GET['sort'] ?? '', ['old', 'tags', 'title'], true) ? $_GET['sort'] : 'new';
        $prio = $level($_GET['priority'] ?? null, 0);   // 0 = all priorities
        $w = ['(n.user_id = ? OR n.user_id IS NULL)']; $a = [$uid];
        if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $w[] = '(n.title LIKE ? OR n.body LIKE ? OR n.tags LIKE ? OR n.theme LIKE ?)'; array_push($a, $like, $like, $like, $like); }
        if ($tag !== '') { $w[] = 'n.tags LIKE ?'; $a[] = '%,' . addcslashes($tag, '%_\\') . ',%'; }
        if ($prio) { $w[] = 'n.priority = ?'; $a[] = $prio; }
        $order = ['new' => 'n.id DESC', 'old' => 'n.id ASC', 'tags' => "(n.tags = ''), n.tags, n.id DESC", 'title' => "(n.title = ''), n.title, n.id DESC"][$sort];
        $notes = $db->rows('SELECT n.*, f.name AS author FROM {p}hello_notes n LEFT JOIN {p}users f ON f.id = n.from_user_id WHERE ' . implode(' AND ', $w) . " ORDER BY $order LIMIT " . ($prio ? 1000 : 200), $a);   // a priority selection shows all its notes
        $counts = [1 => 0, 2 => 0, 3 => 0];   // notes of the user per priority
        foreach ($db->rows('SELECT priority, COUNT(*) AS c FROM {p}hello_notes WHERE (user_id = ? OR user_id IS NULL) GROUP BY priority', [$uid]) as $r) $counts[$level($r['priority'])] += (int)$r['c'];
        $all = [];   // every tag of the user with its count
        foreach ($db->rows("SELECT tags FROM {p}hello_notes WHERE (user_id = ? OR user_id IS NULL) AND tags <> ''", [$uid]) as $r) foreach (array_filter(explode(',', $r['tags'])) as $t) $all[$t] = ($all[$t] ?? 0) + 1;
        ksort($all);
        $themes = array_column($db->rows("SELECT theme FROM {p}hello_notes WHERE (user_id = ? OR user_id IS NULL) AND theme <> '' GROUP BY theme ORDER BY theme LIMIT 200", [$uid]), 'theme');   // autocomplete
        $edit = isset($_GET['edit']) ? $mine((int)$_GET['edit']) : null;
        $sf->render('index', ['notes' => $notes, 'q' => $q, 'tag' => $tag, 'sort' => $sort, 'prio' => $prio, 'counts' => $counts, 'allTags' => $all, 'themes' => $themes, 'uid' => $uid, 'edit' => $edit,
            'users' => $db->rows("SELECT id, name FROM {p}users WHERE status = 'active' AND id <> ? ORDER BY name LIMIT 500", [$uid]), 'myEmail' => (string)($user['email'] ?? '')], $sf->t('title'));
    }, ['cap' => 'hello.use']);

    // dashboard widget: the latest notes of the signed-in user
    $sf->on('dashboard.widgets', function (array $w) use ($sf, $me) {
        if (!$sf->can('hello.use')) return $w;
        $db = $sf->db(); $u = $me(); $cards = '';
        $n = (int)$db->val('SELECT COUNT(*) FROM {p}hello_notes WHERE user_id = ? OR user_id IS NULL', [$u]);
        foreach ($db->rows('SELECT title, body, created_at FROM {p}hello_notes WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC LIMIT 3', [$u]) as $r) {
            $parts = preg_split('/\R/', trim((string)$r['body']), 2); $head = trim((string)$r['title']) !== '' ? (string)$r['title'] : (string)$parts[0]; $rest = trim((string)$r['title'] !== '' ? (string)$r['body'] : (string)($parts[1] ?? ''));
            $cards .= '<a class="dw-i dw-note" href="' . e($sf->url('/')) . '"><b>' . e(mb_substr($head, 0, 60)) . '</b> <small>' . e(ago((string)$r['created_at'])) . '</small>' . ($rest !== '' ? '<span>' . e(mb_substr($rest, 0, 110)) . '</span>' : '') . '</a>';
        }
        $w['notes'] = '<div class="dw-w"><div class="dw-h"><b>' . e($sf->t('title')) . '</b><span class="muted">' . $n . '</span><a class="btn sm" href="' . e($sf->url('/')) . '">' . e($sf->t('open')) . '</a></div>' . ($cards !== '' ? '<div class="dw-list">' . $cards . '</div>' : '') . '</div>';
        return $w;
    });
};
