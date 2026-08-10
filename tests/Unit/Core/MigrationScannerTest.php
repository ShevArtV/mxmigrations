<?php

/**
 * Тесты обхода каталога миграций (#2608-12): порядок и отсев служебных файлов.
 */

namespace MxMigrations\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MxMigrations\Core\MigrationFile;
use MxMigrations\Core\MigrationScanner;

class MigrationScannerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sg-migrations-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        // Именно scandir: glob не видит файлы, начинающиеся с точки, а такой в фикстурах есть.
        foreach ((array)scandir($this->dir) as $entry) {
            if (is_string($entry) && $entry !== '.' && $entry !== '..') {
                unlink($this->dir . '/' . $entry);
            }
        }
        rmdir($this->dir);
    }

    public function testSortsByNameAndSkipsServiceFiles(): void
    {
        $this->write('20260805_1200__second.php', '<?php return function ($modx) {};');
        $this->write('20260101_0900__first.php', '<?php return function ($modx) {};');
        $this->write('migrate.php', '<?php // сам раннер');
        $this->write('README.md', '# док');
        $this->write('notes.txt', 'заметка');
        $this->write('.hidden.php', '<?php return function ($modx) {};');

        $files = (new MigrationScanner())->scan($this->dir);

        $names = array_map(static function (MigrationFile $file): string {
            return $file->getName();
        }, $files);

        self::assertSame(['20260101_0900__first.php', '20260805_1200__second.php'], $names);
    }

    public function testDetectsLegacyBootstrap(): void
    {
        $this->write('20260101_0900__legacy.php', "<?php\ndefine('MODX_API_MODE', true);\nrequire 'index.php';");
        $this->write('20260101_1000__modern.php', '<?php return function ($modx) {};');

        $files = (new MigrationScanner())->scan($this->dir);

        self::assertTrue($files[0]->isLegacyBootstrap(), 'файл сам поднимает MODX — нужен отдельный процесс');
        self::assertFalse($files[1]->isLegacyBootstrap());
    }

    public function testChecksumIgnoresLineEndings(): void
    {
        $this->write('20260101_0900__lf.php', "SELECT 1;\nSELECT 2;\n");
        $this->write('20260101_1000__crlf.php', "SELECT 1;\r\nSELECT 2;\r\n");

        $files = (new MigrationScanner())->scan($this->dir);

        self::assertSame(
            $files[0]->getChecksum(),
            $files[1]->getChecksum(),
            'смена окончаний строк не должна выглядеть как правка миграции'
        );
    }

    public function testMissingDirectoryIsAnError(): void
    {
        $this->expectException(\RuntimeException::class);

        (new MigrationScanner())->scan($this->dir . '/nope');
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->dir . '/' . $name, $contents);
    }
}
