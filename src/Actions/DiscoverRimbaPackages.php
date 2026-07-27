<?php

declare(strict_types=1);

namespace Rimba\Base\Actions;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;

class DiscoverRimbaPackages
{
    protected string $cacheKey = 'rimba_packages';

    /**
     * Scan the vendor/rimba folder and return an array of folder names.
     */
    public function execute(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget($this->cacheKey);
        }

        return Cache::rememberForever($this->cacheKey, function () {
            $dir = base_path('vendor/rimba');
            $folders = [];

            if (! is_dir($dir)) {
                return $folders;
            }

            $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) {
                    $folders[] = $fileInfo->getFilename();
                }
            }

            return $folders;
        });
    }
}
