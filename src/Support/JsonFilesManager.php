<?php

declare(strict_types=1);

namespace Rimba\Base\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class JsonFilesManager
{
    protected string $filePath;

    /**
     * @param  string  $filename  The name of the file without extension (e.g., 'terminologies')
     */
    public function __construct(string $filename)
    {
        $this->filePath = storage_path("setup/json/{$filename}.json");
    }

    /**
     * Fetch all records from the parameterized JSON file wrapped in a Collection.
     */
    public function all(): Collection
    {
        if (! File::exists($this->filePath)) {
            return collect([]);
        }

        $jsonContent = File::get($this->filePath);
        $data = json_decode($jsonContent, true);

        return collect(is_array($data) ? $data : []);
    }

    /**
     * Update or merge an individual record matching a specific key.
     */
    public function updateByOrigin(string $originalOrigin, array $newData): bool
    {
        if (! File::exists($this->filePath)) {
            return false;
        }

        $systems = $this->all()->all();
        $updated = false;

        foreach ($systems as $key => $system) {
            if ($this->normalize($system['name'] ?? '') === $this->normalize($originalOrigin)) {
                $systems[$key] = array_merge($system, $newData);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            File::put(
                $this->filePath,
                json_encode($systems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        return $updated;
    }

    private function normalize(string $origin): string
    {
        return rtrim(strtolower(trim($origin)), '/');
    }
}
