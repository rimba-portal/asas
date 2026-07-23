<?php

namespace Rimba\Base\Actions;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;

class DiscoverRimbaPackages
{
    protected string $cacheKey = 'rimba_discovered_packages';

    /**
     * Scan the vendor/rimba folder and map package names to namespaces.
     */
    public function execute(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget($this->cacheKey);
        }

        return Cache::rememberForever($this->cacheKey, function () {
            $dir = base_path('vendor/rimba');
            $discovered = [];

            if (!is_dir($dir)) {
                return $discovered;
            }

            $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) {
                    $folderName = $fileInfo->getFilename(); 
                    $packageKey = 'rimba/' . $folderName;   

                    // Convert folder name to StudlyCase (e.g., "blog-plugin" -> "BlogPlugin")
                    $studlyName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $folderName)));
                    $namespace = 'Rimba\\' . $studlyName;  

                    $discovered[$packageKey] = $namespace;
                }
            }

            return $discovered;
        });
    }
}
