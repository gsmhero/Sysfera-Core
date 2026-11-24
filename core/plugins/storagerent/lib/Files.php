<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/** Files on disk (documents, meter photos, listing photos): random names, a fixed folder outside the web root, extension and image checks. The names in the database are the only way in. */
final class Files
{
    public static function dir(): string
    {
        $d = (defined('SF_ROOT') ? SF_ROOT : dirname(__DIR__, 3)) . '/storage/storagerent'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d;
    }
    public static function validName(string $stored): bool { return preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $stored) === 1; }
    /** Moves an uploaded file in and returns its stored name. Images are verified to be images (and re-checked by size). */
    public static function store(string $tmp, string $ext, bool $image, ?string $dir = null): string
    {
        if ($image) { $i = @getimagesize($tmp); if (!$i || !in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new Err('file_type'); }
        $name = bin2hex(random_bytes(16)) . '.' . $ext; $to = ($dir ?? self::dir()) . '/' . $name;
        if (!(is_uploaded_file($tmp) ? move_uploaded_file($tmp, $to) : @copy($tmp, $to))) throw new Err('file_failed', '', 500);
        @chmod($to, 0644); return $name;
    }
    public static function path(string $stored, ?string $dir = null): ?string
    {
        if (!self::validName($stored)) return null; $p = ($dir ?? self::dir()) . '/' . $stored; return is_file($p) ? $p : null;
    }
    public static function remove(string $stored, ?string $dir = null): void { $p = self::path($stored, $dir); if ($p) @unlink($p); }
    public static function mime(string $ext): string
    {
        return ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8'][$ext] ?? 'application/octet-stream';
    }
    /** Files that a browser could run as a page are always downloaded, never shown inline. */
    public static function inline(string $ext): bool { return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true); }
}
