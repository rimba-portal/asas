<?php

declare(strict_types=1);

namespace Rimba\Base\Services;

use JsonException;
use RuntimeException;

class JsonStore
{
    public function __construct(
        protected string $store,
        protected string $source,
    ) {}

    public function path(): string
    {
        return storage_path(
            "private/{$this->store}.json"
        );
    }

    public function all(): array
    {
        $this->ensureExists();

        $contents = file_get_contents($this->path());

        if ($contents === false) {
            throw new RuntimeException(
                "Unable to read JSON store [{$this->store}]."
            );
        }

        if (trim($contents) === '') {
            return [];
        }

        try {
            $records = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException("Invalid JSON in store [{$this->store}]: {$jsonException->getMessage()}", $jsonException->getCode(), previous: $jsonException);
        }

        if (! is_array($records)) {
            throw new RuntimeException(
                "JSON store [{$this->store}] must contain an array."
            );
        }

        return array_values($records);
    }

    public function find(string $key): ?array
    {
        foreach ($this->all() as $record) {
            if (($record['key'] ?? null) === $key) {
                return $record;
            }
        }

        return null;
    }

    public function exists(string $key): bool
    {
        return $this->find($key) !== null;
    }

    public function create(array $record): void
    {
        $key = $this->extractKey($record);

        if ($this->exists($key)) {
            throw new RuntimeException(
                "A record with key [{$key}] already exists."
            );
        }

        $records = $this->all();
        $records[] = $record;

        $this->save($records);
    }

    public function update(string $key, array $data): void
    {
        $records = $this->all();
        $updated = false;

        foreach ($records as &$record) {
            if (($record['key'] ?? null) !== $key) {
                continue;
            }

            $record = array_merge($record, $data);
            $updated = true;

            break;
        }

        unset($record);

        if (! $updated) {
            throw new RuntimeException(
                "Record with key [{$key}] was not found."
            );
        }

        $newKey = $this->extractKey($data);

        if ($newKey !== $key) {
            foreach ($records as $record) {
                if (($record['key'] ?? null) === $newKey) {
                    throw new RuntimeException(
                        "A record with key [{$newKey}] already exists."
                    );
                }
            }
        }

        $this->save($records);
    }

    public function delete(string $key): void
    {
        $records = array_values(
            array_filter(
                $this->all(),
                static fn (array $record): bool => ($record['key'] ?? null) !== $key,
            )
        );

        $this->save($records);
    }

    public function save(array $records): void
    {
        $this->ensureExists();

        try {
            $contents = json_encode(
                array_values($records),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException("Unable to encode JSON store [{$this->store}]: {$jsonException->getMessage()}", $jsonException->getCode(), previous: $jsonException);
        }

        $written = file_put_contents(
            $this->path(),
            $contents.PHP_EOL,
            LOCK_EX,
        );

        if ($written === false) {
            throw new RuntimeException(
                "Unable to write JSON store [{$this->store}]."
            );
        }
    }

    protected function ensureExists(): void
    {
        $target = $this->path();
        $directory = dirname($target);

        if (is_file($target)) {
            return;
        }

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                "Unable to create JSON storage directory [{$directory}]."
            );
        }

        if (is_file($this->source)) {
            if (! copy($this->source, $target)) {
                throw new RuntimeException(
                    "Unable to copy default JSON store from [{$this->source}]."
                );
            }

            return;
        }

        $written = file_put_contents(
            $target,
            '[]'.PHP_EOL,
            LOCK_EX,
        );

        if ($written === false) {
            throw new RuntimeException(
                "Unable to create JSON store [{$target}]."
            );
        }
    }

    protected function extractKey(array $record): string
    {
        $key = trim((string) ($record['key'] ?? ''));

        if ($key === '') {
            throw new RuntimeException(
                'Every JSON record must have a non-empty key.'
            );
        }

        return $key;
    }
}
