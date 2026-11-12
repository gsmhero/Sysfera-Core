<?php
declare(strict_types=1);
namespace Sysfera;

/** Orders and invoices: a Repo with child lines and automatic numbers. */
final class Documents extends Repo
{
    public function __construct(Db $db, string $table, array $fields, string $event, private string $linesTable, private string $fk, private string $numberKind)
    {
        parent::__construct($db, $table, $fields, $event);
    }

    /** Creates the document with its lines in one go. Lines: [title, qty, price, sku?, ref?, meta?]. Returns the id. */
    public function createWithLines(array $data, array $lines): int
    {
        $data['number'] ??= App::nextNumber($this->numberKind);
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $total = 0.0; foreach ($lines as $l) $total += (float)($l['qty'] ?? 1) * (float)($l['price'] ?? 0);
            $data[$this->table === 'orders' ? 'total' : 'amount'] ??= round($total, 2);
            $id = parent::create($data);
            foreach ($lines as $l) {
                $this->db->q("INSERT INTO {p}{$this->linesTable} ({$this->fk}, title, sku, qty, price, ref, meta) VALUES (?,?,?,?,?,?,?)",
                    [$id, (string)($l['title'] ?? ''), $l['sku'] ?? null, (float)($l['qty'] ?? 1), (float)($l['price'] ?? 0), $l['ref'] ?? null, json_encode($l['meta'] ?? [], JSON_UNESCAPED_UNICODE)]);
            }
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return $id;
    }

    public function lines(int $id): array
    {
        return array_map(function ($r) { $r['meta'] = json_decode((string)$r['meta'], true) ?? []; return $r; },
            $this->db->rows("SELECT * FROM {p}{$this->linesTable} WHERE {$this->fk} = ? ORDER BY id", [$id]));
    }
}
