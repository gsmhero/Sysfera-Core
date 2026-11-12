<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * The object a plugin receives in plugin.php:  return function (\Sysfera\Plugin $sf) { ... };
 * It is the plugin's whole API to the platform (see docs/PLUGIN-SDK.md).
 */
final class Plugin
{
    public function __construct(public readonly string $slug, public readonly array $manifest, public readonly string $dir, private App $app) {}

    /** Page under /p/<slug>/... ; $opt: 'cap' => required capability, 'auth' => false for public pages. */
    public function route(string $method, string $path, callable $fn, array $opt = []): void
    {
        $this->app->router()->add($method, '/p/' . $this->slug . '/' . ltrim($path, '/'), $fn, $opt + ['plugin' => $this->slug, 'cap' => null, 'auth' => true]);
    }

    /** Sidebar entry. $label is a translation key of this plugin (e.g. "menu") or plain text. */
    public function menu(string $label, string $path, array $opt = []): void
    {
        $this->app->addMenu(['label' => $label, 'slug' => $this->slug, 'path' => '/p/' . $this->slug . '/' . ltrim($path, '/'), 'cap' => $opt['cap'] ?? null, 'order' => $opt['order'] ?? 50, 'icon' => $opt['icon'] ?? null]);
    }

    public function on(string $hook, callable $fn, int $priority = 10): void { Hooks::on($hook, $fn, $priority); }
    public function t(string $key, array $vars = []): string { return t($this->slug . '.' . $key, $vars); }
    public function db(): Db { return $this->app->db(); }
    public function auth(): Auth { return $this->app->auth(); }
    public function can(string $cap): bool { return $this->app->auth()->can($cap); }
    public function url(string $path = ''): string { return url('/p/' . $this->slug . ($path === '' ? '' : '/' . ltrim($path, '/'))); }
    public function asset(string $file): string { return url('/assets/p/' . $this->slug . '/' . ltrim($file, '/')); }

    /** Shared records (same for every plugin). */
    public function contacts(): Repo { return $this->app->contacts(); }
    public function orders(): Documents { return $this->app->orders(); }
    public function invoices(): Documents { return $this->app->invoices(); }

    /** Plugin-private settings, stored in the shared settings table as plugin.<slug>.<key>. */
    public function setting(string $k, string $default = ''): string { return $this->app->setting("plugin.{$this->slug}.$k", $default); }
    public function setSetting(string $k, string $v): void { $this->app->setSetting("plugin.{$this->slug}.$k", $v); }

    /** Render views/<name>.php of the plugin inside the platform layout. */
    public function render(string $view, array $vars = [], ?string $title = null): void
    {
        View::page($title ?? $this->t('title'), View::file($this->dir . "/views/$view.php", $vars + ['sf' => $this]));
    }

    /** Write to the shared audit log. */
    public function log(string $type, array $data = []): void
    {
        $this->app->db()->q('INSERT INTO {p}events (user_id, plugin, type, data) VALUES (?,?,?,?)', [$this->app->auth()->id() ?: null, $this->slug, $type, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }
}
