<?php

declare(strict_types=1);

namespace Rimba\Base\Services;

class JsonStore
{
    public function __construct(
        protected string $store,
        protected ?string $source = null,
    ) {}

    public function path(): string
    {
        return storage_path(
            "private/{$this->store}.json"
        );
    }

    protected function ensureExists(): void
    {
        $target = $this->path();

        if (file_exists($target)) {
            return;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        if (
            $this->source &&
            file_exists($this->source)
        ) {
            copy($this->source, $target);

            return;
        }

        file_put_contents(
            $target,
            json_encode([], JSON_PRETTY_PRINT)
        );
    }

    public function all(): array
    {
        $this->ensureExists();

        return json_decode(
            file_get_contents($this->path()),
            true
        ) ?? [];
    }

    public function save(array $records): void
    {
        file_put_contents(
            $this->path(),
            json_encode(
                array_values($records),
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
            )
        );
    }

    public function create(array $record): void
    {
        $records = $this->all();

        $records[] = $record;

        $this->save($records);
    }

    public function update(string $name, array $data): void
    {
        $records = collect($this->all())
            ->map(fn (array $record): array => ($record['name'] ?? null) === $name
                    ? array_merge($record, $data)
                    : $record)

            ->values()
            ->all();

        $this->save($records);
    }

    public function delete(string $name): void
    {
        $records = collect($this->all())
            ->reject(fn (array $record): bool => ($record['name'] ?? null) === $name)

            ->values()
            ->all();

        $this->save($records);
    }
}
