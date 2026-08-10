<?php

/**
 * Обход каталога миграций.
 *
 * Порядок применения — по имени файла, поэтому конвенция `YYYYMMDD_HHMM__slug`
 * даёт хронологический порядок сама собой. Baseline-файлы без метки (`fix_*.php`)
 * в неё не укладываются, но они помечены применёнными и выполняться не будут —
 * их взаимный порядок значения не имеет.
 *
 * Миграции бывают только `.php`: поддержку `.sql` убрали — за всё время
 * работы раннера её не использовала ни одна миграция.
 */

namespace MxMigrations\Core;

/**
 * @author Arthur Shevchenko (https://t.me/ShevArtV)
 */
class MigrationScanner
{
    /** Служебные файлы каталога: сам раннер и его документация миграциями не являются. */
    private const IGNORED = ['migrate.php', 'README.md'];

    /**
     * @return array<int, MigrationFile> Отсортированы по имени.
     */
    public function scan(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException('Каталог миграций не найден: ' . $dir);
        }

        $files = [];
        foreach ((array)scandir($dir) as $entry) {
            if (!is_string($entry) || $entry === '' || $entry[0] === '.') {
                continue;
            }
            if (in_array($entry, self::IGNORED, true)) {
                continue;
            }

            $path = rtrim($dir, '/\\') . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== MigrationFile::TYPE_PHP) {
                continue;
            }

            $files[] = new MigrationFile($path);
        }

        usort($files, static function (MigrationFile $a, MigrationFile $b): int {
            return strcmp($a->getName(), $b->getName());
        });

        return $files;
    }
}
