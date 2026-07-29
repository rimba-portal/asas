<?php

declare(strict_types=1);

namespace Rimba\Base\Support;

use Rimba\Base\Services\GetModelInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class JsonSeedThruModel extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $directoryPath = database_path('seeds');

        if (! File::exists($directoryPath)) {
            $this->command?->error("Directory not found at: {$directoryPath}");

            return;
        }

        foreach (File::files($directoryPath) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $jsonContent = File::get($file->getRealPath());
            $jsonMap = json_decode($jsonContent, true);

            if (empty($jsonMap) || ! is_array($jsonMap)) {
                $this->command?->warn("Skipping file: '{$file->getFilename()}'. JSON is empty or invalid.");

                continue;
            }

            // Extract the table name from the 1st layer key
            foreach ($jsonMap as $tableName => $data) {

                if (! Schema::hasTable($tableName)) {
                    $this->command?->warn(
                        "Skipping table '{$tableName}'. Table does not exist."
                    );

                    continue;
                }

                $modelClass = GetModelInfo::findByTable($tableName);

                if (! $modelClass) {
                    $this->command?->warn(
                        "Skipping table '{$tableName}'. No model found."
                    );

                    continue;
                }

                if (! $this->isList($data)) {
                    $data = [$data];
                }

                $this->command?->info(
                    "Model seeding table '{$tableName}' using {$modelClass}..."
                );

                foreach ($data as $row) {
                    $this->seedRow($modelClass, $row);
                }
            }
        }

        $this->command?->info('Model JSON directory seeding completed successfully!');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function seedRow(string $modelClass, array $row): Model
    {
        /** @var Model $model */
        $model = new $modelClass;

        [$attributes, $relations] = $this->splitAttributesAndRelations($model, $row);

        $attributes = $this->resolveSeedMappings($model, $attributes);

        $uniqueBy = $this->guessUniqueBy($attributes);

        if ($uniqueBy === []) {
            /** @var Model $record */
            $record = $modelClass::query()->create($attributes);
        } else {
            /** @var Model $record */
            $record = $modelClass::query()->updateOrCreate(
                $uniqueBy,
                $attributes
            );
        }

        foreach ($relations as $relationName => $items) {
            $this->seedRelation($record, $relationName, $items);
        }

        return $record;
    }

    protected function splitAttributesAndRelations(Model $model, array $row): array
    {
        $attributes = [];
        $relations = [];

        foreach ($row as $key => $value) {
            if (
                is_array($value)
                && method_exists($model, $key)
                && $this->isRelation($model, $key)
            ) {
                $relations[$key] = $value;

                continue;
            }

            $attributes[$key] = $value;
        }

        return [$attributes, $relations];
    }

    protected function isRelation(Model $model, string $method): bool
    {
        try {
            return $model->{$method}() instanceof Relation;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function seedRelation(Model $parent, string $relationName, array $items): void
    {
        if (! $this->isList($items)) {
            $items = [$items];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $uniqueBy = $this->guessUniqueBy($item);

            if ($uniqueBy === []) {
                $parent->{$relationName}()->create($item);

                continue;
            }

            $parent->{$relationName}()->updateOrCreate(
                $uniqueBy,
                $item
            );
        }
    }

    protected function guessUniqueBy(array $attributes): array
    {
        foreach (['key', 'code', 'slug', 'value', 'name'] as $column) {
            if (array_key_exists($column, $attributes)) {
                return [
                    $column => $attributes[$column],
                ];
            }
        }

        return [];
    }

    protected function isList(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    protected function resolveSeedMappings(
        Model $model,
        array $attributes
    ): array {
        if (! method_exists($model, 'seedMappings')) {
            return $attributes;
        }

        $mappings = $model::seedMappings();

        foreach ($mappings as $key => $resolver) {

            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $value = $attributes[$key];

            unset($attributes[$key]);

            $attributes = array_merge(
                $attributes,
                $resolver($value)
            );
        }

        return $attributes;
    }
}
