<?php
declare(strict_types=1);
/** Interface sets in memory, for tests/interfacesets.php. */
final class InterfaceSetsMemStore implements \Sysfera\SetStore
{
    private array $rows = [];
    private int $next = 1;

    public function all(int $user): array
    {
        $out = [];
        foreach ($this->rows as $id => $r) if ($r['user'] === $user) $out[] = ['id' => $id, 'name' => $r['name'], 'items' => $r['items'], 'active' => $r['active']];
        return $out;
    }
    public function create(int $user, string $name): int { $this->rows[$this->next] = ['user' => $user, 'name' => $name, 'items' => [], 'active' => false]; return $this->next++; }
    public function update(int $user, int $id, string $name, array $items): void { if (($this->rows[$id]['user'] ?? null) === $user) $this->rows[$id] = ['name' => $name, 'items' => $items] + $this->rows[$id]; }
    public function delete(int $user, int $id): void { if (($this->rows[$id]['user'] ?? null) === $user) unset($this->rows[$id]); }
    public function activate(int $user, int $id): void { foreach ($this->rows as $k => &$r) if ($r['user'] === $user) $r['active'] = $k === $id; }
    public function deleteUser(int $user): void { $this->rows = array_filter($this->rows, fn($r) => $r['user'] !== $user); }
}
