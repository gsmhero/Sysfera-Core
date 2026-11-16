<?php
declare(strict_types=1);

/** Example component. Read docs/PLUGIN-SDK.md — this file shows every basic building block in ~50 lines. */
return function (\Sysfera\Plugin $sf): void {
    // 1) a sidebar entry (label = translation key of this plugin, see lang/*.php)
    $sf->menu('menu', '/', ['cap' => 'hello.use', 'order' => 30, 'icon' => 'puzzle']);

    // 2) pages: /p/hello/ (list + add) — admins pass every capability check automatically
    $sf->route('*', '/', function () use ($sf) {
        $db = $sf->db();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $text = trim((string)($_POST['text'] ?? ''));
            if (!empty($_POST['delete'])) { $db->q('DELETE FROM {p}hello_notes WHERE id = ?', [(int)$_POST['delete']]); }
            elseif ($text !== '') {
                $name = trim((string)($_POST['client'] ?? ''));
                $cid = null;
                if ($name !== '') {   // shared clients: create (or reuse) a contact — CRM, Accounting etc. will see the same client
                    $found = $sf->contacts()->list(['name' => $name], 1);
                    $cid = $found ? (int)$found[0]['id'] : $sf->contacts()->create(['name' => $name, 'source' => 'hello', 'owner_id' => $sf->auth()->id()]);
                }
                $db->q('INSERT INTO {p}hello_notes (contact_id, user_id, body) VALUES (?,?,?)', [$cid, $sf->auth()->id(), mb_substr($text, 0, 2000)]);
                $sf->log('note.added', ['contact_id' => $cid]);
                flash($sf->t('added'));
            }
            redirect('/p/hello/');
        }
        $notes = $db->rows('SELECT n.*, c.name AS client FROM {p}hello_notes n LEFT JOIN {p}contacts c ON c.id = n.contact_id ORDER BY n.id DESC LIMIT 100');
        $sf->render('index', ['notes' => $notes], $sf->t('title'));
    }, ['cap' => 'hello.use']);

    // 3) a dashboard widget through a filter hook
    $sf->on('dashboard.widgets', function (array $w) use ($sf) {
        if (!$sf->can('hello.use')) return $w;
        $db = $sf->db(); $n = (int)$db->val('SELECT COUNT(*) FROM {p}hello_notes'); $cards = '';
        foreach ($db->rows('SELECT body, created_at FROM {p}hello_notes ORDER BY id DESC LIMIT 3') as $r) {
            $parts = preg_split('/\R/', trim((string)$r['body']), 2); $head = mb_substr(trim((string)$parts[0]), 0, 60); $rest = trim((string)($parts[1] ?? ''));
            $cards .= '<a class="dw-i dw-note" href="' . e($sf->url('/')) . '"><b>' . e($head) . '</b> <small>' . e(ago((string)$r['created_at'])) . '</small>' . ($rest !== '' ? '<span>' . e(mb_substr($rest, 0, 110)) . '</span>' : '') . '</a>';
        }
        $w['notes'] = '<div class="dw-w"><div class="dw-h"><b>' . e($sf->t('title')) . '</b><span class="muted">' . $n . '</span><a class="btn sm" href="' . e($sf->url('/')) . '">' . e($sf->t('open')) . '</a></div>' . ($cards !== '' ? '<div class="dw-list">' . $cards . '</div>' : '') . '</div>';
        return $w;
    });

    // 4) react to shared events from other components
    $sf->on('contact.created', function (int $id, ?array $c) use ($sf) { /* e.g. send a welcome note */ });
};
