<?php

namespace MxMigrations\Platform\Modx2;

use MxMigrations\Model\ModelFilesystem;
use MxMigrations\Model\SchemaDocument;

final class ModelBuilder
{
    private \modX $modx;
    private ModelFilesystem $files;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
        $this->files = new ModelFilesystem();
    }

    /** @return array{added: array<int, string>, changed: array<int, string>, removed: array<int, string>} */
    public function build(string $schemaPath, string $modelPath, bool $apply): array
    {
        $root = sys_get_temp_dir() . '/mxmigrations-model-' . getmypid() . '-' . bin2hex(random_bytes(4));
        try {
            if (!@mkdir($root, 0775, true) && !is_dir($root)) { throw new \RuntimeException('Не удалось создать ' . $root); }
            $manager = $this->modx->getManager();
            $generator = $manager ? $manager->getGenerator() : null;
            if (!$generator) { throw new \RuntimeException('xPDO generator недоступен.'); }
            $generator->parseSchema($schemaPath, $root . '/');
            $this->files->normalize($root);
            $built = $this->files->generatedRoot($root, (new SchemaDocument($schemaPath))->getPackage());
            $managed = static function (string $relative): bool {
                $relative = str_replace('\\', '/', strtolower($relative));
                return strpos($relative, 'mysql/') === 0
                    || basename($relative) === 'metadata.mysql.php';
            };
            $diff = $this->files->diff($modelPath, $built, $managed);
            if ($apply) { $this->files->apply($modelPath, $built, $managed); }
            return $diff;
        } finally {
            $this->files->removeTree($root);
        }
    }
}
