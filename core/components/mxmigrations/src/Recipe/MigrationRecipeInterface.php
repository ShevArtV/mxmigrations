<?php

namespace MxMigrations\Recipe;

interface MigrationRecipeInterface
{
    public function getName(): string;
    public function getDescription(): string;

    /** @return array<string, string> option => description */
    public function getOptions(): array;

    /** @param array<string, mixed> $arguments */
    public function render(string $migrationName, array $arguments): string;
}
