<?php
declare(strict_types=1);
namespace SysferaWms;

use RuntimeException;

/**
 * Request handling of "Products → Import": upload → Fields Converter → preview → import → report.
 * One controller for GET and POST; state lives in a small working file under core/storage/wms-import (private to the owner).
 */
final class ImportFlow
{
    /** @param callable $warehouses fn(): list of the owner's active warehouses */
    public static function handle(\Sysfera\Plugin $sf, \Sysfera\Db $db, Stock $stock, Docs $docs, callable $warehouses, callable $page): void
    {
        $owner = (int)(Scope::id() ?: $sf->auth()->id());
        $cat = ImportWms::catalog();
        $self = fn(string $q = '') => wms_path('/import') . $q;
        ImportWms::sweep();

        $token = (string)($_POST['t'] ?? $_GET['t'] ?? '');
        $job = $token !== '' ? ImportWms::load($owner, $token) : null;
        if ($token !== '' && !$job) { flash($sf->t('imp_expired'), 'bad'); $token = ''; }
        $do = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? (string)($_POST['do'] ?? '') : '';
        $table = function (array $t): array {      // keep the working file small: cut huge cells
            foreach ($t['rows'] as $i => $r) foreach ($r as $c => $x) if (ImportKit::len($x) > 6000) $t['rows'][$i][$c] = ImportKit::cut($x, 6000);
            return $t;
        };
        $sheetsInfo = fn(array $sheets) => array_map(fn($s) => ['name' => $s['name'], 'rows' => count($s['rows']), 'hidden' => !empty($s['hidden'])], $sheets);
        $read = function (string $path, string $name, ?int $sheet = null, ?int $hdr = null) use ($sf, $cat): array {
            try {
                $sheets = SheetReader::read($path, $name);
                $t = ImportKit::detect($sheets, $cat, $sheet, $hdr);
                if (!$t) throw new RuntimeException('headers');
                if (!$t['rows']) throw new RuntimeException('norows');
                if (count($t['rows']) > ImportKit::MAX_ROWS) throw new RuntimeException('toomany:' . count($t['rows']));
                return [$sheets, $t, null];
            } catch (RuntimeException $e) {
                $m = $e->getMessage();
                $msg = $m === 'headers' ? $sf->t('imp_err_headers') : ($m === 'norows' ? $sf->t('imp_err_norows') : (str_starts_with($m, 'toomany:') ? $sf->t('imp_err_rows', ['n' => substr($m, 8), 'max' => ImportKit::MAX_ROWS]) : $sf->t('imp_err_read') . ' (' . $m . ')'));
                return [null, null, $msg];
            }
        };

        /* ---- upload ---- */
        if ($do === 'upload') {
            $f = $_FILES['file'] ?? null;
            $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
            if (!$f || ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE) flash($sf->t('imp_choose'), 'bad');
            elseif (($f['error'] ?? 0) !== UPLOAD_ERR_OK || $f['size'] > SheetReader::MAX_BYTES) flash($sf->t('imp_too_big', ['mb' => SheetReader::MAX_BYTES >> 20]), 'bad');
            elseif (!in_array($ext, ['xlsx', 'xlsm', 'csv', 'txt', 'tsv'], true)) flash($sf->t('imp_bad_ext'), 'bad');
            else {
                [$sheets, $t, $err] = $read($f['tmp_name'], (string)$f['name']);
                if ($err) flash($err, 'bad');
                else {
                    $token = bin2hex(random_bytes(12));
                    move_uploaded_file($f['tmp_name'], (string)ImportWms::path($owner, $token, 'src'));
                    ImportWms::save($owner, $token, ['name' => mb_substr(basename((string)$f['name']), 0, 160), 'sheets' => $sheetsInfo($sheets), 'table' => $table($t), 'choice' => null, 'opts' => ['stock' => 'new', 'wh' => 0], 'created' => time()]);
                    redirect($self('?t=' . $token));
                }
            }
            redirect($self());
        }
        if ($job && $do === 'cancel') {
            foreach (['src', 'json'] as $x) { $p = ImportWms::path($owner, $token, $x); if ($p) @unlink($p); }
            flash($sf->t('imp_cancelled')); redirect(wms_path('/products'));
        }
        if ($job && $do === 'reparse') {
            [, $t, $err] = $read((string)ImportWms::path($owner, $token, 'src'), (string)$job['name'], max(0, (int)($_POST['sheet'] ?? 0)), max(1, (int)($_POST['header_row'] ?? 1)) - 1);
            if ($err) flash($err, 'bad'); else { $job['table'] = $table($t); $job['choice'] = null; unset($job['report']); ImportWms::save($owner, $token, $job); }
            redirect($self('?t=' . $token));
        }

        /* ---- templates of this owner ---- */
        $templates = [];
        foreach ($db->rows('SELECT id, name, signature, mapping FROM {p}wms_import_templates WHERE owner_id = ? ORDER BY updated_at DESC', [$owner]) as $r) {
            $m = json_decode((string)$r['mapping'], true);
            if (is_array($m)) $templates[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'signature' => $r['signature'], 'mapping' => $m];
        }
        $whs = array_values(array_filter($warehouses(), fn($w) => !$w['use_cells']));   // opening stock goes to a warehouse without cells, like the product card

        $planAll = function (array $job, array $choice, array $opts) use ($db, $owner, $cat): array {
            $rows = ImportKit::extract($job['table'], $choice, $cat);
            $dupe = ImportWms::duplicates($rows);
            $out = [];
            foreach ($rows as $r) {
                if (isset($dupe[$r['line']])) { $out[] = ['row' => $r, 'plan' => ['action' => 'error', 'errors' => [['duplicate', ['line' => $dupe[$r['line']]]]], 'warnings' => [], 'd' => []], 'ex' => null]; continue; }
                $ex = ImportWms::find($db, $owner, $r);
                $out[] = ['row' => $r, 'plan' => ImportWms::plan($r, $ex, $opts), 'ex' => $ex];
            }
            return $out;
        };

        /* ---- converter submitted -> preview ---- */
        if ($job && $do === 'preview') {
            $job['choice'] = ImportKit::cleanChoice($job['table']['headers'], (array)($_POST['field'] ?? []), (array)($_POST['name'] ?? []), $cat);
            $job['ticked'] = array_map('intval', array_keys(array_filter((array)($_POST['tpl'] ?? []))));
            $job['tpl_name'] = mb_substr(trim((string)($_POST['tpl_name'] ?? '')), 0, 190);
            $mode = (string)($_POST['stock'] ?? 'new');
            $wh = (int)($_POST['wh'] ?? 0);
            $job['opts'] = ['stock' => in_array($mode, ['new', 'set', 'none'], true) ? $mode : 'new', 'wh' => in_array($wh, array_map(fn($w) => (int)$w['id'], $whs), true) ? $wh : (int)($whs[0]['id'] ?? 0)];
            unset($job['report']);
            ImportWms::save($owner, $token, $job);
            redirect($self('?t=' . $token . '&step=preview'));
        }

        /* ---- run ---- */
        if ($job && $do === 'run' && !empty($job['choice']) && empty($job['report'])) {
            @ignore_user_abort(true); @set_time_limit(300);
            $opts = $job['opts']; $rep = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => [], 'warnings' => [], 'docs' => [], 'units' => 0.0];
            $ticked = array_flip($job['ticked'] ?? []);
            $tm = ImportKit::templateMapping($job['table']['headers'], $job['choice'], $ticked);
            if ($tm) {
                $nm = $job['tpl_name'] !== '' ? $job['tpl_name'] : (preg_replace('/[_\s]*\d{2}\.\d{2}\.\d{4}$/u', '', pathinfo((string)$job['name'], PATHINFO_FILENAME)) ?: 'Template');
                $db->q('INSERT INTO {p}wms_import_templates (owner_id, name, signature, mapping) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name), mapping = VALUES(mapping)',
                    [$owner, mb_substr($nm, 0, 190), ImportKit::signature($job['table']['headers']), json_encode($tm, JSON_UNESCAPED_UNICODE)]);
                $rep['template'] = $nm;
            }
            $receipt = []; $count = [];
            foreach ($planAll($job, $job['choice'], $opts) as $x) {
                $r = $x['row']; $plan = $x['plan'];
                if ($plan['action'] === 'error') { $rep['failed']++; if (count($rep['errors']) < 300) $rep['errors'][] = [$r['line'], $plan['errors']]; continue; }
                try {
                    $res = ImportWms::apply($db, $owner, $plan, $x['ex']);
                    $res['created'] ? $rep['created']++ : $rep['updated']++;
                    foreach ($plan['warnings'] as $w) if (count($rep['warnings']) < 300) $rep['warnings'][] = [$r['line'], $w];
                    if (isset($plan['d']['qty']) && $opts['stock'] !== 'none') {
                        $buy = (float)($plan['d']['buy'] ?? ($x['ex']['buy_price'] ?? 0));
                        if ($res['created'] && $plan['d']['qty'] > 0) $receipt[] = ['product_id' => $res['id'], 'qty' => $plan['d']['qty'], 'price' => $buy];
                        elseif (!$res['created'] && $opts['stock'] === 'set') $count[] = ['product_id' => $res['id'], 'qty' => $plan['d']['qty'], 'price' => $buy];
                    }
                } catch (\Throwable $e) {
                    $rep['failed']++; if (count($rep['errors']) < 300) $rep['errors'][] = [$r['line'], [['save_failed', []]]];
                    \Sysfera\App::log('wms import row ' . $r['line'] . ': ' . $e->getMessage());
                }
            }
            /* stock: new products get a posted receipt, "set" also books an inventory count for the existing ones */
            foreach (['receipt' => $receipt, 'inventory' => $count] as $type => $lines) {
                if (!$lines || !$opts['wh']) continue;
                try {
                    $id = $docs->save(['type' => $type, 'warehouse_id' => $opts['wh'], 'moment' => date('Y-m-d H:i:s'), 'comment' => $sf->t('imp_doc_comment', ['file' => $job['name']])], $lines, null, $sf->auth()->id() ?: null);
                    $bad = $docs->post($id);
                    $d = $docs->find($id);
                    $rep['docs'][] = ['id' => $id, 'number' => (string)($d['number'] ?? ''), 'type' => $type, 'lines' => count($lines), 'ok' => !$bad];
                    if (!$bad && $type === 'receipt') $rep['units'] += array_sum(array_column($lines, 'qty'));
                } catch (\Throwable $e) { $rep['docs'][] = ['id' => 0, 'number' => '', 'type' => $type, 'lines' => count($lines), 'ok' => false]; \Sysfera\App::log('wms import doc: ' . $e->getMessage()); }
            }
            $sf->log('import.done', ['file' => $job['name'], 'created' => $rep['created'], 'updated' => $rep['updated'], 'failed' => $rep['failed']]);
            $job['report'] = $rep;
            ImportWms::save($owner, $token, $job);
            $p = ImportWms::path($owner, $token, 'src'); if ($p) @unlink($p);
            redirect($self('?t=' . $token . '&step=done'));
        }

        /* ================= views ================= */
        $step = $job ? (string)($_GET['step'] ?? 'map') : 'upload';
        if ($job && $step === 'done' && empty($job['report'])) $step = 'map';
        if ($job && $step === 'preview' && empty($job['choice'])) $step = 'map';
        $vars = ['tab' => 'products', 'job' => $job, 'token' => $token, 'step' => $step, 'cat' => $cat, 'templates' => $templates, 'whs' => $whs, 'owner' => $owner];
        if ($job && $step === 'preview') $vars['plans'] = $planAll($job, $job['choice'], $job['opts']);
        $page('import', $vars, $sf->t('imp_title'), 'products');
    }
}
