<?php

namespace MxMigrations\Tests\Unit\Recipe;

use MxMigrations\Recipe\BuiltinRecipeProvider;
use MxMigrations\Recipe\RecipeRegistry;
use MxMigrations\Tests\Fixtures\ProjectRecipeProvider;
use PHPUnit\Framework\TestCase;

final class RecipeRegistryTest extends TestCase
{
    public function testBuiltinsAndProjectRecipesShareOneRegistry(): void
    {
        $registry = new RecipeRegistry();
        $registry->addProvider(new BuiltinRecipeProvider());
        $registry->addProvider(new ProjectRecipeProvider());

        self::assertSame('добавить колонку', $registry->get('add-column')->getDescription());
        self::assertSame(
            'проектная миграция тестового виджета',
            $registry->get('project:seed-widget')->getDescription()
        );
    }

    public function testDuplicateRecipeIsRejected(): void
    {
        $registry = new RecipeRegistry();
        $registry->addProvider(new ProjectRecipeProvider());
        $this->expectException(\RuntimeException::class);
        $registry->addProvider(new ProjectRecipeProvider());
    }

    public function testProjectRecipeProducesStandalonePhp(): void
    {
        $recipe = (new ProjectRecipeProvider())->getRecipes()[0];
        $body = $recipe->render('20260810_1700__widget.php', ['widget' => 'hero']);
        $path = tempnam(sys_get_temp_dir(), 'mxmigrations-recipe-');
        self::assertNotFalse($path);
        file_put_contents($path, $body);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
        unlink($path);

        self::assertSame(0, $code, implode("\n", $output));
        self::assertStringNotContainsString('ProjectRecipe', $body);
    }
}
