<?php

use MxMigrations\Cli\Application;
use MxMigrations\Config\ProjectConfig;

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit('mxMigrations запускается только из CLI.');
}

require_once dirname(__DIR__) . '/autoload.php';

$arguments = array_slice($argv, 1);
$configPath = getenv('MXMIGRATIONS_CONFIG') ?: '';
foreach ($arguments as $index => $argument) {
    if (strpos($argument, '--config=') === 0) {
        $configPath = substr($argument, strlen('--config='));
        unset($arguments[$index]);
    }
}
if ($configPath === '') {
    fwrite(STDERR, 'Укажите --config=/path/to/migrations.php или MXMIGRATIONS_CONFIG.' . PHP_EOL);
    exit(1);
}

try {
    exit((new Application(ProjectConfig::fromFile($configPath)))->run(array_values($arguments)));
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
