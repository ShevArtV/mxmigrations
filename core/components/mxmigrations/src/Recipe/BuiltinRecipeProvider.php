<?php

namespace MxMigrations\Recipe;

final class BuiltinRecipeProvider implements MigrationRecipeProviderInterface
{
    public function getRecipes(): array
    {
        $templates = new BuiltinTemplates();
        $options = [
            'drop-settings' => ['class' => 'класс настройки', 'keys' => 'ключи через запятую'],
            'drop-tables' => ['tables' => 'таблицы без префикса'],
            'drop-elements' => ['class' => 'класс элемента', 'names' => 'имена элементов'],
            'add-column' => ['table' => 'таблица', 'column' => 'колонка', 'type' => 'SQL-определение'],
            'modify-column' => ['table' => 'таблица', 'column' => 'колонка', 'type' => 'SQL-определение'],
            'drop-column' => ['table' => 'таблица', 'column' => 'колонка'],
            'add-index' => ['table' => 'таблица', 'name' => 'имя индекса', 'columns' => 'колонки', 'unique' => 'уникальный'],
            'drop-index' => ['table' => 'таблица', 'name' => 'имя индекса'],
            'create-tables' => ['package' => 'имя xPDO-пакета', 'model-path' => 'путь к модели', 'classes' => 'классы'],
        ];
        $recipes = [];
        foreach (BuiltinTemplates::RECIPES as $name => $description) {
            $recipes[] = new BuiltinRecipe($name, $description, $templates, $options[$name] ?? []);
        }

        return $recipes;
    }
}
