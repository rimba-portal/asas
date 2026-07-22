<?php

declare(strict_types=1);

namespace Bites\Base\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GetModelInfo
{
    /**
     * Return all discovered Eloquent model classes.
     *
     * @return Collection<int, class-string<Model>>
     */
    public static function all(): Collection
    {
        return collect(self::tableMap())
            ->values()
            ->unique()
            ->values();
    }

    /**
     * Build a table-name to model-class map.
     *
     * Example:
     * [
     *     'attribute_definitions' => 'Bites\Attributing\Models\AttributeDefinition',
     *     'attribute_options' => 'Bites\Attributing\Models\AttributeOption',
     * ]
     *
     * @return array<string, class-string<Model>>
     */
    public static function tableMap(): array
    {
        $results = [];

        foreach (self::scanDirectories() as $directory) {
            self::scanDirectory($directory, $results);
        }

        ksort($results);

        return $results;
    }

    /**
     * Find an Eloquent model class by table name.
     *
     * @return class-string<Model>|null
     */
    public static function findByTable(string $tableName): ?string
    {
        return self::tableMap()[$tableName] ?? null;
    }

    /**
     * Directories to scan for models.
     *
     * @return array<int, string>
     */
    protected static function scanDirectories(): array
    {
        return array_values(array_filter([
            app_path(),

            // Local package development path.
            base_path('packages'),

            // Installed package paths that you care about.
            base_path('vendor/bit-es'),
            base_path('vendor/rimba'),
        ], static fn (string $directory): bool => is_dir($directory)));
    }

    /**
     * Scan PHP files recursively and collect table => model mappings.
     *
     * @param  array<string, class-string<Model>>  $results
     */
    protected static function scanDirectory(string $directory, array &$results): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();

            if (! is_string($path)) {
                continue;
            }

            /*
             * Optional but useful optimization.
             * Only scan files inside a Models directory.
             *
             * Matching examples:
             * app/Models/User.php
             * packages/bit-es/attributing/src/Models/AttributeDefinition.php
             * vendor/bit-es/attributing/src/Models/AttributeDefinition.php
             */
            if (! str_contains(str_replace('\\', '/', $path), '/Models/')) {
                continue;
            }

            $contents = file_get_contents($path);

            if (! is_string($contents)) {
                continue;
            }

            /*
             * Fast skip.
             *
             * This works for:
             * class AttributeDefinition extends Model
             *
             * If later you use:
             * class Staff extends BaseModel
             *
             * then remove this block and let instanceof Model check below decide.
             */
            if (! str_contains($contents, 'extends Model')) {
                continue;
            }

            $fqcn = self::extractClassName($contents);

            if (! $fqcn) {
                continue;
            }

            try {
                if (! class_exists($fqcn)) {
                    continue;
                }

                $instance = new $fqcn;

                if (! $instance instanceof Model) {
                    continue;
                }

                $results[$instance->getTable()] = $fqcn;
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * Extract the fully qualified class name from PHP file contents.
     */
    protected static function extractClassName(string $contents): ?string
    {
        preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch);
        preg_match('/class\s+([A-Za-z_]\w*)/', $contents, $classMatch);

        $namespace = $namespaceMatch[1] ?? null;
        $className = $classMatch[1] ?? null;

        if (! $namespace || ! $className) {
            return null;
        }

        return trim($namespace).'\\'.trim($className);
    }
}
