<?php

namespace MxMigrations\Recipe;

use MxMigrations\Config\ProjectConfig;

final class RecipeRegistryFactory
{
    public function create(ProjectConfig $config): RecipeRegistry
    {
        foreach ($config->getAutoload() as $autoload) {
            if (!is_file($autoload)) {
                throw new \RuntimeException('Project autoload не найден: ' . $autoload);
            }
            require_once $autoload;
        }

        $registry = new RecipeRegistry();
        $registry->addProvider(new BuiltinRecipeProvider());
        foreach ($config->getRecipeProviders() as $class) {
            if (!class_exists($class)) {
                throw new \RuntimeException('Класс провайдера рецептов не найден: ' . $class);
            }
            $provider = new $class();
            if (!$provider instanceof MigrationRecipeProviderInterface) {
                throw new \RuntimeException($class . ' должен реализовывать MigrationRecipeProviderInterface.');
            }
            $registry->addProvider($provider);
        }

        return $registry;
    }
}
