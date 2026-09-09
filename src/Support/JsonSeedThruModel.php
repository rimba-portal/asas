<?php

declare(strict_types=1);

namespace Rimba\Base\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Rimba\Base\Services\GetModelInfo;

class JsonSeedThruModel extends Seeder
{
    protected int $totalCount = 0;

    protected int $createdCount = 0;

    protected int $updatedCount = 0;

    protected int $skippedCount = 0;

    public function __construct(
        protected ?string $directoryPath = null,
    ) {}

    public function run(): void
    {
        $directoryPath = $this->directoryPath
            ?? database_path('seeds');

        if (! File::exists($directoryPath)) {
            $this->command?->error(
                "Directory not found: {$directoryPath}"
            );

            return;
        }

        foreach (File::files($directoryPath) as $file) {

            if ($file->getExtension() !== 'json') {
                continue;
            }

            $this->command?->warn("Doing: {$file->getFilename()}");
            $json = json_decode(
                File::get($file->getRealPath()),
                true
            );

            if (
                empty($json) ||
                ! is_array($json)
            ) {
                $this->command?->warn(
                    "Invalid JSON: {$file->getFilename()}"
                );

                continue;
            }

            foreach ($json as $tableName => $rows) {

                if (! Schema::hasTable($tableName)) {

                    $this->command?->warn(
                        "Skipping table '{$tableName}' - table not found."
                    );

                    continue;
                }

                $modelClass = GetModelInfo::findByTable(
                    $tableName
                );

                if (! $modelClass) {

                    $this->command?->warn(
                        "Skipping table '{$tableName}' - model not found."
                    );

                    continue;
                }

                if (! $this->isList($rows)) {
                    $rows = [$rows];
                }

                $this->command?->info(
                    sprintf(
                        "Model seeding table '%s' using %s...",
                        $tableName,
                        $modelClass
                    )
                );

                $beforeTotal = $this->totalCount;
                $beforeCreated = $this->createdCount;
                $beforeUpdated = $this->updatedCount;

                foreach ($rows as $row) {

                    $this->seedRow(
                        $modelClass,
                        $row
                    );
                }

                $this->command?->info(sprintf(
                    'Rows=%d Created=%d Updated=%d',
                    $this->totalCount - $beforeTotal,
                    $this->createdCount - $beforeCreated,
                    $this->updatedCount - $beforeUpdated,
                ));
            }
        }

        $this->command?->info(
            'JSON seeding completed successfully.'
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function seedRow(
        string $modelClass,
        array $row
    ): Model {

        /** @var Model $model */
        $model = new $modelClass;

        [
            $attributes,
            $relations,
        ] = $this->splitAttributesAndRelations(
            $model,
            $row
        );

        $attributes = $this->resolveSeedMappings(
            $model,
            $attributes
        );

        $uniqueBy = $this->guessUniqueBy(
            $model
        );

        /*
    |--------------------------------------------------------------------------
    | Create if no unique key found
    |--------------------------------------------------------------------------
    */
        if ($uniqueBy === []) {

            $record = $modelClass::query()->create(
                $attributes
            );

            $this->createdCount++;
            $this->totalCount++;
        } else {

            /*
        |--------------------------------------------------------------------------
        | Find existing
        |--------------------------------------------------------------------------
        */
            $record = $modelClass::query()
                ->where($uniqueBy)
                ->first();

            /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */
            if (! $record) {

                $record = $modelClass::query()->create(
                    $attributes
                );

                $this->createdCount++;
            }

            /*
        |--------------------------------------------------------------------------
        | Update only if changed
        |--------------------------------------------------------------------------
        */ else {

                $record->fill(
                    $attributes
                );

                if ($record->isDirty()) {

                    $record->save();

                    $this->updatedCount++;
                } else {

                    $this->skippedCount++;
                }
            }

            $this->totalCount++;
        }

        foreach ($relations as $relationName => $items) {

            $this->seedRelation(
                $record,
                $relationName,
                $items
            );
        }

        return $record;
    }

    protected function seedRelation(
        Model $parent,
        string $relationName,
        array $items
    ): void {

        if (! $this->isList($items)) {
            $items = [$items];
        }

        $relation = $parent->{$relationName}();

        $relatedModel = $relation->getRelated();

        foreach ($items as $item) {

            if (! is_array($item)) {
                continue;
            }

            [
                $attributes,
                $nestedRelations,
            ] = $this->splitAttributesAndRelations(
                $relatedModel,
                $item
            );

            /*
        |--------------------------------------------------------------------------
        | Inject Relationship Keys
        |--------------------------------------------------------------------------
        */
            switch (true) {

                /*
            |--------------------------------------------------------------------------
            | HasOne / HasMany
            |--------------------------------------------------------------------------
            */
                case $relation instanceof HasOne:
                case $relation instanceof HasMany:

                    $attributes[$relation->getForeignKeyName()] = $parent->getKey();

                    break;

                    /*
            |--------------------------------------------------------------------------
            | MorphOne / MorphMany
            |--------------------------------------------------------------------------
            */
                case $relation instanceof MorphOne:
                case $relation instanceof MorphMany:

                    $attributes[$relation->getForeignKeyName()] = $parent->getKey();

                    $attributes[$relation->getMorphType()] = $parent->getMorphClass();

                    break;
            }

            $record = $this->seedRow(
                get_class($relatedModel),
                array_merge(
                    $attributes,
                    $nestedRelations
                )
            );

            if ($relation instanceof BelongsToMany) {
                $relation->syncWithoutDetaching([
                    $record->getKey(),
                ]);
            }
        }
    }

    protected function splitAttributesAndRelations(
        Model $model,
        array $row
    ): array {

        $attributes = [];
        $relations = [];

        foreach ($row as $key => $value) {

            if (
                is_array($value) &&
                method_exists($model, $key) &&
                $this->isRelation($model, $key)
            ) {
                $relations[$key] = $value;

                continue;
            }

            $attributes[$key] = $value;
        }

        return [
            $attributes,
            $relations,
        ];
    }

    protected function isRelation(
        Model $model,
        string $method
    ): bool {

        try {
            return $model->{$method}()
                instanceof Relation;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function guessUniqueBy(
        Model $model,
        array $attributes
    ): array {

        $table = $model->getTable();

        foreach (
            Schema::getIndexes($table) as $index
        ) {

            if ($index['primary'] ?? false) {
                continue;
            }

            if (! ($index['unique'] ?? false)) {
                continue;
            }

            $columns = $index['columns'] ?? [];

            if (
                empty(array_diff(
                    $columns,
                    array_keys($attributes)
                ))
            ) {

                return collect($columns)
                    ->mapWithKeys(
                        fn ($column): array => [
                            $column => $attributes[$column],
                        ]
                    )
                    ->all();
            }
        }

        return [];
    }

    protected function resolveSeedMappings(
        Model $model,
        array $attributes
    ): array {

        if (! method_exists(
            $model,
            'seedMappings'
        )) {
            return $attributes;
        }

        $mappings = $model::seedMappings();

        foreach ($mappings as $key => $resolver) {

            if (
                ! array_key_exists(
                    $key,
                    $attributes
                )
            ) {
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

    protected function isList(
        array $array
    ): bool {

        return array_keys($array)
            === range(
                0,
                count($array) - 1
            );
    }
}
