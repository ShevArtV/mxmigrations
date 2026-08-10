<?php

namespace MxMigrations\Recipe;

interface MigrationRecipeProviderInterface
{
    /** @return array<int, MigrationRecipeInterface> */
    public function getRecipes(): array;
}
