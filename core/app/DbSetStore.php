<?php
declare(strict_types=1);
namespace Sysfera;

/** Interface sets in the {p}ui_sets table. */
final class DbSetStore implements SetStore
{
    public function __construct(private Db $db) {}

    public function all(int $user): array
    {
        return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'items' => array_values((array)(json_decode((string)$r['items'], true) ?: [])), 'active' => (bool)$r['active']],
            $this->db->rows('SELECT id, name, items, active FROM {p}ui_sets WHERE user_id = ? ORDER BY id', [$user]));
    }
    public function create(int $user, string $name): int
    {
        $this->db->q('INSERT INTO {p}ui_sets (user_id, name, items) VALUES (?,?,?)', [$user, $name, '[]']);
        return $this->db->insertId();
    }
    public function update(int $user, int $id, string $name, array $items): void
    {
        $this->db->q('UPDATE {p}ui_sets SET name = ?, items = ? WHERE id = ? AND user_id = ?', [$name, json_encode(array_values($items)), $id, $user]);
    }
    public function delete(int $user, int $id): void { $this->db->q('DELETE FROM {p}ui_sets WHERE id = ? AND user_id = ?', [$id, $user]); }
    public function activate(int $user, int $id): void { $this->db->q('UPDATE {p}ui_sets SET active = (id = ?) WHERE user_id = ?', [$id, $user]); }
    public function deleteUser(int $user): void { $this->db->q('DELETE FROM {p}ui_sets WHERE user_id = ?', [$user]); }
}
