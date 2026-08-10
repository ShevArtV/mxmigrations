<?php

/**
 * Контракт исполнителя миграции.
 */

namespace MxMigrations\Core;

/**
 * @author Arthur Shevchenko (https://t.me/ShevArtV)
 */
interface ExecutorInterface
{
    /**
     * Выполняет миграцию и возвращает её вывод.
     *
     * @throws \RuntimeException если миграция не выполнилась: прогон должен встать
     *                           на первом же сбое, а не идти дальше по списку.
     */
    public function execute(MigrationFile $file): string;
}
