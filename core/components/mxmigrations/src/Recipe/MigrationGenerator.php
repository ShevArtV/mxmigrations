<?php

namespace MxMigrations\Recipe;

use MxMigrations\Core\MigrationWriter;

final class MigrationGenerator
{
    private MigrationWriter $writer;
    private RecipeRegistry $recipes;

    public function __construct(MigrationWriter $writer, RecipeRegistry $recipes)
    {
        $this->writer = $writer;
        $this->recipes = $recipes;
    }

    /** @param array<string, mixed> $arguments
     *  @return array{name: string, body: string, path: string|null}
     */
    public function generate(string $slug, string $recipeName, array $arguments, bool $write): array
    {
        $name = $this->writer->nextName($slug);
        $body = $this->recipes->get($recipeName)->render($name, $arguments);
        $path = $write ? $this->writer->writeNamed($name, $body) : null;

        return ['name' => $name, 'body' => $body, 'path' => $path];
    }
}
