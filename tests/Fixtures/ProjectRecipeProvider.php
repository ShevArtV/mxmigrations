<?php

namespace MxMigrations\Tests\Fixtures;

use MxMigrations\Recipe\MigrationRecipeProviderInterface;

final class ProjectRecipeProvider implements MigrationRecipeProviderInterface
{
    public function getRecipes(): array
    {
        return [new ProjectRecipe()];
    }
}
