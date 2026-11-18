<?php
declare(strict_types=1);
namespace SysferaCrm;

/** Rules for the files attached to task comments (pure: no database, no filesystem). */
final class Discuss
{
    public const MAX_FILES = 5;
    public const MAX_BYTES = 10_000_000;
    public const MAX_BODY = 4000;
    public const EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'zip', '7z'];
    public const IMAGES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    /** A file name that is safe to show and to put into a header: no path, no control characters, at most 150 characters. */
    public static function safeName(string $n): string
    {
        $n = str_replace(['\\', '/', "\0", "\r", "\n", '"'], ['_', '_', '', ' ', ' ', '_'], basename(str_replace('\\', '/', $n)));
        $n = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $n) ?? '', ". \t");
        if (function_exists('mb_substr') && mb_strlen($n) > 150) { $e = pathinfo($n, PATHINFO_EXTENSION); $n = mb_substr(pathinfo($n, PATHINFO_FILENAME), 0, 140) . ($e !== '' ? '.' . $e : ''); }
        return $n;
    }

    public static function ext(string $name): string { return strtolower(pathinfo($name, PATHINFO_EXTENSION)); }
    public static function extOk(string $name): bool { return in_array(self::ext($name), self::EXT, true); }

    /** $_FILES['files'] (the multiple-input shape) -> a list of single files; empty slots are dropped. */
    public static function list(?array $f): array
    {
        if (!$f || !isset($f['name'])) return [];
        if (!is_array($f['name'])) return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        $out = [];
        foreach ($f['name'] as $i => $n) if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $out[] = ['name' => (string)$n, 'tmp_name' => (string)($f['tmp_name'][$i] ?? ''), 'size' => (int)($f['size'][$i] ?? 0), 'error' => (int)$f['error'][$i]];
        return $out;
    }

    /** null when the list is fine, else the language key of the problem. */
    public static function check(array $files): ?string
    {
        if (count($files) > self::MAX_FILES) return 'e_files_many';
        foreach ($files as $f) {
            if (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || (int)($f['size'] ?? 0) > self::MAX_BYTES) return 'e_file_big';
            if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) return 'e_file_fail';
            if (!self::extOk((string)$f['name'])) return 'e_file_type';
        }
        return null;
    }
}