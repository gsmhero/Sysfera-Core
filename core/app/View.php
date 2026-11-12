<?php
declare(strict_types=1);
namespace Sysfera;

/** Renders PHP templates inside the shared layout. Templates live in core/views (and plugins/<slug>/views). */
final class View
{
    /** Render a template file to a string. Variables become local variables of the template. */
    public static function file(string $file, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try { require $file; } catch (\Throwable $e) { ob_end_clean(); throw $e; }
        return (string)ob_get_clean();
    }

    public static function render(string $tpl, array $vars = [], ?string $title = null): void
    {
        self::page($title ?? t('nav.dashboard'), self::file(SF_ROOT . "/views/$tpl.php", $vars));
    }

    public static function page(string $title, string $content, array $opt = []): void
    {
        $app = App::get();
        echo self::file(SF_ROOT . '/views/layout.php', [
            'title' => $title, 'content' => $content, 'user' => $app->auth()?->user(),
            'menu' => $opt['bare'] ?? false ? [] : $app->menu(), 'bare' => $opt['bare'] ?? false,
            'siteName' => $app->setting('site_name', 'Sysfera'), 'flashes' => flashes(),
        ]);
    }
}
