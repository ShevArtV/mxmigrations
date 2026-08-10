<?php

/**
 * Контракт блокировки прогона миграций.
 */

namespace MxMigrations\Core;

/**
 * @author Arthur Shevchenko (https://t.me/ShevArtV)
 */
interface LockInterface
{
    /** @return bool false — блокировку уже держит другой процесс. */
    public function acquire(): bool;

    public function release(): void;
}
