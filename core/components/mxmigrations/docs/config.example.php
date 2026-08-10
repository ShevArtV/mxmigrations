<?php

return [
    'id' => 'example',
    'modx_root' => dirname(__DIR__, 4),
    'migrations_path' => dirname(__DIR__, 4) . '/core/components/example/migrations',
    'journal_table' => 'example_migrations',
    'lock_name' => 'example_migrations',
    'autoload' => [dirname(__DIR__, 4) . '/core/components/example/vendor/autoload.php'],
    'recipe_providers' => [Example\Migrations\RecipeProvider::class],
];
