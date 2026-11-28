<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Interface sets: a user's own named choices of which components the menu panel shows.
 * They only hide menu entries — nothing is disabled or uninstalled and no permission changes.
 * Entries that belong to no component (Dashboard, the core administration pages) are always shown, so nobody can lock themselves out.
 */
final class InterfaceSets
{
    public const MAX_SETS = 20;
    public const MAX_NAME = 60;

    private array $cache = [];

    public function __construct(private SetStore $store) {}

    public function all(int $user): array { return $this->cache[$user] ??= $this->store->all($user); }

    public function find(int $user, int $id): ?array
    {
        foreach ($this->all($user) as $s) if ($s['id'] === $id) return $s;
        return null;
    }

    public function active(int $user): ?array
    {
        foreach ($this->all($user) as $s) if ($s['active']) return $s;
        return null;
    }

    /** Menu items left by a set; null = no set chosen, everything stays. Items without a component slug are never removed. */
    public static function filter(array $menu, ?array $set): array
    {
        if ($set === null) return $menu;
        return array_values(array_filter($menu, fn($i) => empty($i['slug']) || in_array($i['slug'], $set['items'], true)));
    }

    /** @return int|string the new set's id, or an error key */
    public function create(int $user, string $name): int|string
    {
        $name = self::clean($name);
        if (count($this->all($user)) >= self::MAX_SETS) return 'ui.too_many';
        if ($err = $this->nameProblem($user, $name, 0)) return $err;
        $id = $this->store->create($user, $name);
        unset($this->cache[$user]);
        return $id;
    }

    /**
     * Renames a set and stores its ticks. Only components in $allowed (those the user can see now) are changed:
     * a tick for a component that is disabled or taken away for a while is kept.
     * @return string|null error key
     */
    public function save(int $user, int $id, string $name, array $checked, array $allowed): ?string
    {
        if (!($set = $this->find($user, $id))) return 'ui.not_found';
        $name = self::clean($name);
        if ($err = $this->nameProblem($user, $name, $id)) return $err;
        $items = array_merge(array_diff($set['items'], $allowed), array_intersect($allowed, array_filter($checked, 'is_string')));
        $this->store->update($user, $id, $name, array_values(array_unique($items)));
        unset($this->cache[$user]);
        return null;
    }

    /** Puts one component into exactly the sets in $ids (the checkboxes under a component on the Components page). */
    public function assign(int $user, string $slug, array $ids): void
    {
        $ids = array_map('intval', array_filter($ids, 'is_scalar'));
        foreach ($this->all($user) as $s) {
            $want = in_array($s['id'], $ids, true);
            if ($want === in_array($slug, $s['items'], true)) continue;
            $this->store->update($user, $s['id'], $s['name'], $want ? [...$s['items'], $slug] : array_values(array_diff($s['items'], [$slug])));
        }
        unset($this->cache[$user]);
    }

    public function delete(int $user, int $id): void { $this->store->delete($user, $id); unset($this->cache[$user]); }

    /** 0 or an unknown id = show everything. */
    public function activate(int $user, int $id): void { $this->store->activate($user, $this->find($user, $id) ? $id : 0); unset($this->cache[$user]); }

    public function deleteUser(int $user): void { $this->store->deleteUser($user); unset($this->cache[$user]); }

    /**
     * One form post of the interface sets (Profile, Dashboard, Components).
     * @param list<string> $allowed component slugs this user has a menu entry for
     * @return array{0: string, 1: ?string, 2: string} page to go to, message key (null = none), message type
     */
    public function handle(int $user, array $post, array $allowed): array
    {
        $str = fn(string $k): string => is_string($post[$k] ?? null) ? $post[$k] : '';
        $list = fn(string $k): array => is_array($post[$k] ?? null) ? $post[$k] : [];
        $id = (int)$str('id');
        switch ($str('do')) {
            case 'create':
                $res = $this->create($user, $str('name'));
                return is_int($res) ? ["/profile?set=$res#interface-sets", 'ui.created', 'ok'] : ['/profile#interface-sets', $res, 'bad'];
            case 'save':
                $err = $this->save($user, $id, $str('name'), $list('items'), $allowed);
                if ($err === null) return ['/profile#interface-sets', 'ui.saved', 'ok'];
                return [($err === 'ui.not_found' ? '/profile' : "/profile?set=$id") . '#interface-sets', $err, 'bad'];
            case 'delete':
                $this->delete($user, $id);
                return ['/profile#interface-sets', 'ui.deleted', 'ok'];
            case 'switch':
                $this->activate($user, (int)$str('set'));
                return ['/', null, 'ok'];
            case 'assign':
                $slug = $str('slug');
                if (!in_array($slug, $allowed, true)) return ['/plugins', 'ui.not_found', 'bad'];
                $this->assign($user, $slug, $list('sets'));
                return ["/plugins#c-$slug", 'ui.saved', 'ok'];
        }
        return ['/profile', null, 'ok'];
    }

    private static function clean(string $name): string
    {
        $name = trim((string)preg_replace('/\s+/u', ' ', $name));
        return preg_match('/^.{0,' . self::MAX_NAME . '}/us', $name, $m) ? rtrim($m[0]) : '';
    }

    private function nameProblem(int $user, string $name, int $self): ?string
    {
        if ($name === '') return 'ui.need_name';
        foreach ($this->all($user) as $s) if ($s['id'] !== $self && mb_strtolower($s['name']) === mb_strtolower($name)) return 'ui.name_taken';
        return null;
    }
}
