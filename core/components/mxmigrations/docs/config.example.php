<?php

$root = dirname(__DIR__, 2);

return [
    'id' => 'site',
    'modx_root' => $root,
    'migrations_path' => $root . '/core/migrations',
    'models' => [
        'site' => [
            'schema_path' => $root . '/core/components/site/model/schema/site.mysql.schema.xml',
            'model_path' => $root . '/core/components/site/model/site',
        ],
        'minishop2' => [
            'schema_path' => $root . '/core/components/minishop2/model/schema/minishop2.mysql.schema.xml',
            'model_path' => $root . '/core/components/minishop2/model/minishop2',
            'overlay' => true,
        ],
    ],
    'autoload' => [$root . '/core/components/site/vendor/autoload.php'],
    'recipe_providers' => [Site\Migrations\RecipeProvider::class],
];
