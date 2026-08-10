<?php

namespace MxMigrations\Recipe;

final class BuiltinRecipe implements MigrationRecipeInterface
{
    private string $name;
    private string $description;
    private BuiltinTemplates $templates;
    /** @var array<string, string> */
    private array $options;

    /** @param array<string, string> $options */
    public function __construct(string $name, string $description, BuiltinTemplates $templates, array $options = [])
    {
        $this->name = $name;
        $this->description = $description;
        $this->templates = $templates;
        $this->options = $options;
    }

    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getOptions(): array { return $this->options; }

    public function render(string $migrationName, array $arguments): string
    {
        return $this->templates->render($this->name, $migrationName, $arguments);
    }
}
