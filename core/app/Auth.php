<?php
declare(strict_types=1);
namespace Sysfera;

/** Sessions, login, registration, roles and capabilities. Roles: admin (everything) and user (only the capabilities granted to them). */
final class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private Db $db) {}

    public function user(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            sf_session();
            if (!empty($_SESSION['uid'])) {
                $u = $this->db->row("SELECT * FROM {p}users WHERE id = ? AND status = 'active'", [(int)$_SESSION['uid']]);
                if ($u) { $u['caps_list'] = json_decode((string)$u['caps'], true) ?: []; $this->user = $u; }
                else unset($_SESSION['uid']);
            }
        }
        return $this->user;
    }

    public function id(): int { return (int)($this->user()['id'] ?? 0); }
    public function isAdmin(): bool { return ($this->user()['role'] ?? '') === 'admin'; }
    public function can(string $cap): bool { $u = $this->user(); return $u && ($u['role'] === 'admin' || in_array($cap, $u['caps_list'], true)); }

    /** @return string|null error key or null on success */
    public function login(string $email, string $password): ?string
    {
        sf_session();
        $_SESSION['tries'] = ($_SESSION['tries'] ?? 0) + 1;
        if ($_SESSION['tries'] > 8 && time() - ($_SESSION['try_t'] ?? 0) < 60) return 'auth.too_many';
        $_SESSION['try_t'] = time();
        $u = $this->db->row('SELECT * FROM {p}users WHERE email = ?', [mb_strtolower(trim($email))]);
        if (!$u || !password_verify($password, $u['password_hash'])) return 'auth.wrong';
        if ($u['status'] !== 'active') return 'auth.blocked';
        $this->start((int)$u['id']);
        return null;
    }

    public function start(int $uid): void
    {
        sf_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid; unset($_SESSION['tries']);
        $this->db->q('UPDATE {p}users SET last_login = NOW() WHERE id = ?', [$uid]);
        $this->loaded = false; $this->user = null;
        Hooks::do('user.login', $uid);
    }

    public function logout(): void
    {
        sf_session();
        $_SESSION = [];
        session_destroy();
        $this->loaded = true; $this->user = null;
    }

    /** @return string|int error key (string) or the new user id (int) */
    public function register(string $email, string $name, string $password, string $role = 'user', ?string $lang = null): string|int
    {
        $email = mb_strtolower(trim($email)); $name = trim($name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'auth.bad_email';
        if ($name === '') return 'auth.need_name';
        if (mb_strlen($password) < 8) return 'auth.short_password';
        if ($this->db->val('SELECT 1 FROM {p}users WHERE email = ?', [$email])) return 'auth.email_taken';
        $this->db->q('INSERT INTO {p}users (email, name, password_hash, role, lang) VALUES (?,?,?,?,?)', [$email, $name, password_hash($password, PASSWORD_DEFAULT), $role, $lang]);
        $id = $this->db->insertId();
        Hooks::do('user.registered', $id);
        return $id;
    }

    /** Page guard. Redirects guests to /login, shows 403 if the capability is missing. */
    public function require(?string $cap = null): array
    {
        $u = $this->user();
        if (!$u) { sf_session(); $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '/'; redirect('/login'); }
        if ($cap !== null && !$this->can($cap)) { http_response_code(403); View::page(t('err.403'), '<div class="card empty"><h2>' . e(t('err.403')) . '</h2><p>' . e(t('err.403_text')) . '</p></div>'); exit; }
        return $u;
    }
}
