<?php

namespace MxMigrations\Recipe;

final class RecipeRegistry
{
    /** @var array<string, MigrationRecipeInterface> */
    private array $recipes = [];

    public function add(MigrationRecipeInterface $recipe): void
    {
        $name = $recipe->getName();
        if (!preg_match('~^[a-z0-9][a-z0-9_-]*(?::[a-z0-9][a-z0-9_-]*)?$~', $name)) {
            throw new \InvalidArgumentException('Недопустимое имя рецепта: ' . $name);
        }
        if (isset($this->recipes[$name])) {
            throw new \RuntimeException('Рецепт зарегистрирован дважды: ' . $name);
        }
        $this->recipes[$name] = $recipe;
        ksort($this->recipes);
    }

    public function addProvider(MigrationRecipeProviderInterface $provider): void
    {
        foreach ($provider->getRecipes() as $recipe) {
            $this->add($recipe);
        }
    }

    public function get(string $name): MigrationRecipeInterface
    {
        if (!isset($this->recipes[$name])) {
            throw new \InvalidArgumentException(
                'Неизвестный рецепт «' . $name . '». Доступны: ' . implode(', ', array_keys($this->recipes)) . '.'
            );
        }

        return $this->recipes[$name];
    }

    /** @return array<string, MigrationRecipeInterface> */
    public function all(): array
    {
        return $this->recipes;
    }
}
