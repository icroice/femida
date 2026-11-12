<?php

namespace App\Support;

/**
 * Escaping LIKE wildcards in user input (%, _, \):
 * without it "100%" becomes a pattern and breaks the search.
 * Works together with the ESCAPE clause in whereRaw — a bare backslash
 * is not treated as an escape character by SQLite/MySQL.
 */
class Like
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** A "contains substring" pattern. */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    /** A "path prefix" pattern — the folder itself + all subfolders. */
    public static function prefix(string $value): string
    {
        return self::escape($value).'/%';
    }
}
