<?php

/**
 * Ядро раннера миграций.
 *
 * Отвечает на три вопроса: что применено, что осталось, что изменилось задним
 * числом. Всё, что зависит от MODX (журнал, исполнение, блокировка), спрятано за
 * интерфейсами — сам класс тестируется без БД.
 */

namespace MxMigrations\Core;

/**
 * @author Arthur Shevchenko (https://t.me/ShevArtV)
 */
class MigrationRunner
{
    /** Миграция выполнена раннером. */
    public const STATUS_APPLIED = 'applied';

    /** Миграция помечена применённой без выполнения (`--baseline`). */
    public const STATUS_BASELINE = 'baseline';

    /** Миграция упала: считается неприменённой и будет предложена снова. */
    public const STATUS_FAILED = 'failed';

    private string $dir;
    private JournalInterface $journal;
    private ExecutorInterface $executor;
    private LockInterface $lock;
    private MigrationScanner $scanner;

    public function __construct(
        string $dir,
        JournalInterface $journal,
        ExecutorInterface $executor,
        LockInterface $lock,
        ?MigrationScanner $scanner = null
    ) {
        $this->dir = $dir;
        $this->journal = $journal;
        $this->executor = $executor;
        $this->lock = $lock;
        $this->scanner = $scanner ?: new MigrationScanner();
    }

    /**
     * Текущее состояние: что применено, что ждёт, что разошлось, что встало не по порядку.
     *
     * @return array{
     *     pending: array<int, MigrationFile>,
     *     applied: array<int, array{file: MigrationFile, record: MigrationRecord}>,
     *     drifted: array<int, array{file: MigrationFile, record: MigrationRecord}>,
     *     missing: array<int, MigrationRecord>,
     *     outOfOrder: array<int, MigrationFile>
     * }
     */
    public function plan(): array
    {
        $this->journal->ensureTable();

        $records = $this->journal->all();
        $files = $this->scanner->scan($this->dir);

        $pending = [];
        $applied = [];
        $drifted = [];
        $seen = [];

        /* Миграции, которых раннер ещё не видел ни разу. Отличаются от pending тем,
           что упавшая миграция в журнале уже есть: её имя когда-то было в очереди
           на своём месте, и «пришельцем из прошлого» она не является. */
        $unknown = [];

        foreach ($files as $file) {
            $name = $file->getName();
            $seen[$name] = true;
            $record = $records[$name] ?? null;

            /* Упавшая миграция — не применённая: показываем её снова в pending,
               чтобы повторный `up` попробовал доиграть прогон с того же места. */
            if (!$record || $record->getStatus() === self::STATUS_FAILED) {
                $pending[] = $file;
                if (!$record) {
                    $unknown[] = $file;
                }
                continue;
            }

            if ($record->getChecksum() !== '' && $record->getChecksum() !== $file->getChecksum()) {
                $drifted[] = ['file' => $file, 'record' => $record];
                continue;
            }

            $applied[] = ['file' => $file, 'record' => $record];
        }

        $missing = [];
        foreach ($records as $name => $record) {
            if (!isset($seen[$name])) {
                $missing[] = $record;
            }
        }

        return [
            'pending' => $pending,
            'applied' => $applied,
            'drifted' => $drifted,
            'missing' => $missing,
            'outOfOrder' => $this->outOfOrder($unknown, $applied, $drifted),
        ];
    }

    /**
     * Новые миграции, которые встают в очередь раньше уже применённых.
     *
     * Разработчик A написал `_1200` и применил; разработчик B написал `_1100` и влил
     * позже. Здесь миграция B применится последней, а на сервере, поднятом с нуля, —
     * в середине. Один и тот же набор файлов даёт разный порядок на разных серверах,
     * и идемпотентность от этого не спасает: «удалить колонку», проехавшая раньше
     * «добавить колонку», нигде не упадёт, а состояние баз разойдётся.
     *
     * Момент выбран не случайно: файла ещё нет в журнале, поэтому переименовать его
     * ничего не стоит. После применения имя становится ключом журнала, и цена правки
     * вырастает до похода по всем серверам.
     *
     * @param array<int, MigrationFile>                                     $unknown
     * @param array<int, array{file: MigrationFile, record: MigrationRecord}> $applied
     * @param array<int, array{file: MigrationFile, record: MigrationRecord}> $drifted
     *
     * @return array<int, MigrationFile>
     */
    private function outOfOrder(array $unknown, array $applied, array $drifted): array
    {
        /* Файлы без метки в расчёте не участвуют с обеих сторон: три baseline-миграции,
           написанные до раннера, начинаются с буквы, а буквы в ASCII больше цифр — они
           всегда последние в сортировке, и по ним «раньше применённых» оказалась бы
           любая новая миграция. */
        $last = null;
        foreach (array_merge($applied, $drifted) as $item) {
            $name = $item['file']->getName();
            if ($item['file']->getLabel() === null) {
                continue;
            }
            if ($last === null || strcmp($name, $last) > 0) {
                $last = $name;
            }
        }

        if ($last === null) {
            return [];
        }

        $found = [];
        foreach ($unknown as $file) {
            if ($file->getLabel() !== null && strcmp($file->getName(), $last) < 0) {
                $found[] = $file;
            }
        }

        return $found;
    }

    /**
     * Применяет недостающие миграции по порядку имён.
     *
     * @return array{
     *     dryRun: bool,
     *     executed: array<int, array{name: string, duration: int, output: string}>,
     *     failed: array{name: string, error: string}|null,
     *     skipped: array<int, string>,
     *     accepted: array<int, string>
     * }
     *
     * @throws \RuntimeException если прогон уже идёт, обнаружен дрейф checksum
     *                           или новая миграция встаёт раньше применённых.
     */
    public function up(
        bool $dryRun = false,
        bool $ignoreChecksum = false,
        string $appliedBy = '',
        bool $allowOutOfOrder = false
    ): array {
        $plan = $this->plan();
        $this->assertNoDrift($plan['drifted'], $ignoreChecksum);

        /* Сухой прогон не отказывает: он и нужен, чтобы посмотреть, что происходит.
           Печатает предупреждение вызывающая сторона. */
        if (!$dryRun) {
            $this->assertInOrder($plan['outOfOrder'], $allowOutOfOrder);
        }

        $result = [
            'dryRun' => $dryRun,
            'executed' => [],
            'failed' => null,
            'skipped' => [],
            'accepted' => [],
        ];

        if ($dryRun) {
            foreach ($plan['pending'] as $file) {
                $result['executed'][] = ['name' => $file->getName(), 'duration' => 0, 'output' => ''];
            }
            $result['accepted'] = array_map(
                static function (array $item): string {
                    return $item['file']->getName();
                },
                $ignoreChecksum ? $plan['drifted'] : []
            );

            return $result;
        }

        /* Пустая очередь ещё не значит «делать нечего»: с --ignore-checksum надо
           принять новое содержимое изменённых файлов, иначе флаг пришлось бы
           передавать при каждом следующем запуске. */
        if (!$plan['pending'] && !($ignoreChecksum && $plan['drifted'])) {
            return $result;
        }

        if (!$this->lock->acquire()) {
            throw new \RuntimeException('Миграции уже выполняет другой процесс — прогон отменён.');
        }

        try {
            /* Список пересчитываем под блокировкой: пока ждали лок, параллельный
               прогон мог часть миграций уже применить. */
            $plan = $this->plan();
            $this->assertNoDrift($plan['drifted'], $ignoreChecksum);
            $result['accepted'] = $this->acceptDrift($plan['drifted'], $ignoreChecksum, $appliedBy);
            $queue = $plan['pending'];

            foreach ($queue as $file) {
                if ($result['failed'] !== null) {
                    $result['skipped'][] = $file->getName();
                    continue;
                }

                $startedAt = microtime(true);
                try {
                    $output = $this->executor->execute($file);
                    $duration = (int)round((microtime(true) - $startedAt) * 1000);
                    $this->journal->record(new MigrationRecord(
                        $file->getName(),
                        $file->getChecksum(),
                        self::STATUS_APPLIED,
                        time(),
                        $appliedBy,
                        $duration,
                        $output
                    ));
                    $result['executed'][] = [
                        'name' => $file->getName(),
                        'duration' => $duration,
                        'output' => $output,
                    ];
                } catch (\Throwable $e) {
                    $duration = (int)round((microtime(true) - $startedAt) * 1000);
                    $this->journal->record(new MigrationRecord(
                        $file->getName(),
                        $file->getChecksum(),
                        self::STATUS_FAILED,
                        time(),
                        $appliedBy,
                        $duration,
                        $e->getMessage()
                    ));
                    $result['failed'] = ['name' => $file->getName(), 'error' => $e->getMessage()];
                }
            }
        } finally {
            $this->lock->release();
        }

        return $result;
    }

    /**
     * Помечает недостающие миграции применёнными, ничего не выполняя.
     *
     * Нужно там, где изменения уже в базе: на dev, где 19 файлов применяли руками,
     * и на первом проде, который поднимается из дампа.
     *
     * @return array<int, string> Имена помеченных файлов.
     */
    public function baseline(string $appliedBy = ''): array
    {
        $plan = $this->plan();
        if (!$plan['pending']) {
            return [];
        }

        if (!$this->lock->acquire()) {
            throw new \RuntimeException('Миграции уже выполняет другой процесс — baseline отменён.');
        }

        $marked = [];
        try {
            foreach ($this->plan()['pending'] as $file) {
                $this->journal->record(new MigrationRecord(
                    $file->getName(),
                    $file->getChecksum(),
                    self::STATUS_BASELINE,
                    time(),
                    $appliedBy,
                    0,
                    'Помечено как применённое без выполнения (--baseline).'
                ));
                $marked[] = $file->getName();
            }
        } finally {
            $this->lock->release();
        }

        return $marked;
    }

    /**
     * Принимает новое содержимое изменённых файлов как эталон (`--ignore-checksum`).
     *
     * Статус записи не меняется: миграция как была применена, так и остаётся —
     * обновляется только checksum, иначе флаг превратился бы в постоянный и
     * проверка перестала бы что-либо значить.
     *
     * @param array<int, array{file: MigrationFile, record: MigrationRecord}> $drifted
     *
     * @return array<int, string> Имена принятых файлов.
     */
    private function acceptDrift(array $drifted, bool $ignoreChecksum, string $appliedBy): array
    {
        if (!$ignoreChecksum || !$drifted) {
            return [];
        }

        $accepted = [];
        foreach ($drifted as $item) {
            $record = $item['record'];
            $this->journal->record(new MigrationRecord(
                $record->getName(),
                $item['file']->getChecksum(),
                $record->getStatus(),
                $record->getAppliedAt(),
                $record->getAppliedBy(),
                $record->getDuration(),
                trim($record->getOutput() . PHP_EOL . sprintf(
                    'Изменение файла принято без выполнения (--ignore-checksum) %s, %s.',
                    date('Y-m-d H:i'),
                    $appliedBy !== '' ? $appliedBy : 'неизвестно кем'
                ))
            ));
            $accepted[] = $record->getName();
        }

        return $accepted;
    }

    /**
     * @param array<int, array{file: MigrationFile, record: MigrationRecord}> $drifted
     */
    private function assertNoDrift(array $drifted, bool $ignoreChecksum): void
    {
        if (!$drifted || $ignoreChecksum) {
            return;
        }

        $names = array_map(
            static function (array $item): string {
                return $item['file']->getName();
            },
            $drifted
        );

        throw new \RuntimeException(
            'Применённые миграции изменены после применения: ' . implode(', ', $names) . '. '
            . 'Верните файлы в прежний вид или запустите с --ignore-checksum, если расхождение осознанное.'
        );
    }

    /**
     * Отказать в прогоне, если новая миграция встаёт раньше уже применённых.
     *
     * Отказ, а не предупреждение: пропущенное предупреждение всплывёт через месяц на
     * сервере, поднятом с нуля, где порядок окажется другим. Пока файл не применён,
     * лечится переименованием за секунду.
     *
     * @param array<int, MigrationFile> $outOfOrder
     */
    private function assertInOrder(array $outOfOrder, bool $allowOutOfOrder): void
    {
        if (!$outOfOrder || $allowOutOfOrder) {
            return;
        }

        $names = array_map(
            static function (MigrationFile $file): string {
                return $file->getName();
            },
            $outOfOrder
        );

        throw new \RuntimeException(
            'Эти миграции встают в очередь раньше уже применённых: ' . implode(', ', $names) . '. '
            . 'На сервере, поднятом с нуля, они выполнятся в другом порядке. '
            . 'Переименуйте файл на метку в конце очереди (он ещё не в журнале, это бесплатно) '
            . 'или запустите с --allow-out-of-order, если зависимости от соседних миграций нет.'
        );
    }
}
