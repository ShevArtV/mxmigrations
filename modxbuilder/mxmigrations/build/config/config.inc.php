<?php

define('COMPONENT_BUILD', false);
$root = dirname(__DIR__, 4) . '/';
$builderRoot = $root . 'modxbuilder/';

$buildConfig = [
    'real_package_name' => 'mxMigrations',
    'package_name' => 'mxmigrations',
    'package_version' => '1.0.0',
    'package_release' => 'beta',
    'package_table_prefix' => 'mxmigrations_',
    'package_class_prefix' => 'MxMigrations',
    'regenerate_schema' => false,
    'regenerate_classes' => false,
    'regenerate_maps' => false,
    'modx_root' => $root,
    'builder_root' => $builderRoot,
    'tools_root' => $builderRoot . 'tools/',
];

$componentRoot = $builderRoot . 'mxmigrations/';
$buildConfig = array_merge($buildConfig, [
    'root' => $root,
    'build' => $componentRoot . 'build/',
    'resolvers' => $componentRoot . 'build/resolvers/',
    'data' => $componentRoot . 'build/data/',
    'source_core' => $root . 'core/components/mxmigrations/',
    'source_assets' => $root . 'assets/components/mxmigrations/',
    'source_docs' => $root . 'core/components/mxmigrations/docs/',
]);

define('MODX_CORE_PATH', $root . 'core/');
define('MODX_BASE_PATH', $root);
define('MODX_BASE_URL', '/');

unset($root, $builderRoot, $componentRoot);
return $buildConfig;
