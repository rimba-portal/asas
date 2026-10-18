<?php

declare(strict_types=1);

namespace Rimba\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Read and display all PHP files recursively and save them to quick.md')]
#[Signature('rimba:quick-md {folder : The path to the folder}')]
class QuickMdPkg extends Command
{
    public function handle(): int
    {
        $folder = $this->argument('folder');

        $rootPath = base_path($folder);

        if (! File::isDirectory($rootPath)) {
            $this->error('Directory does not exist: '.$rootPath);

            return self::FAILURE;
        }

        $directories = File::directories($rootPath);

        foreach ($directories as $directory) {
            $folderName = basename($directory);

            $this->info('Processing: '.$folderName);

            $files = File::allFiles($directory);

            $markdown = "# {$folderName}\n\n";
            $markdown .= '*Generated: '.now()->toDateTimeString().'* '.config('app.timezone')."\n\n";

            $count = 0;

            $dependencies = [];

            /*
             * Current Rimba package namespace.
             *
             * Example:
             *   Base  -> Rimba\Base
             *   Kerja -> Rimba\Kerja
             *   Jawat -> Rimba\Jawat
             */
            $packageRoot = 'Rimba\\'.$folderName;

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $count++;

                $contents = File::get($file->getRealPath());

                /*
                 * Extract imported classes.
                 *
                 * Example:
                 * use Rimba\Jawat\Models\JobRole;
                 * use Illuminate\Support\Collection;
                 */
                preg_match_all(
                    '/^use\s+([^;]+);/mi',
                    $contents,
                    $matches
                );

                foreach ($matches[1] as $class) {
                    $class = trim($class);

                    /*
                     * Exclude framework dependencies.
                     */
                    if (
                        str_starts_with($class, 'Illuminate\\') ||
                        str_starts_with($class, 'Filament\\')
                    ) {
                        continue;
                    }

                    /*
                     * Convert imported class to its package root.
                     *
                     * Example:
                     * Rimba\Jawat\Models\JobRole
                     *
                     * becomes:
                     * Rimba\Jawat
                     */
                    $parts = explode('\\', $class);

                    if (count($parts) < 2) {
                        continue;
                    }

                    $dependency = $parts[0].'\\'.$parts[1];

                    /*
                     * Exclude references to the package itself.
                     *
                     * Example:
                     * While processing Rimba\Kerja,
                     * Rimba\Kerja\Models\Flow is not a dependency.
                     */
                    if ($dependency === $packageRoot) {
                        continue;
                    }

                    $dependencies[] = $dependency;
                }

                $markdown .= "## {$file->getRelativePathname()}\n\n";
                $markdown .= "```php\n";
                $markdown .= $contents;
                $markdown .= "\n```\n\n";
            }

            /*
             * Remove duplicate dependencies and sort them
             * so the output remains deterministic.
             */
            $dependencies = array_values(
                array_unique($dependencies)
            );

            sort($dependencies);

            $markdown .= "\n---\n\n";
            $markdown .= "# Dependencies\n\n";
            $markdown .= "```php\n";
            $markdown .= var_export($dependencies, true);
            $markdown .= "\n```\n";

            $outputFile = $rootPath
                .DIRECTORY_SEPARATOR
                .'quick_'
                .$folderName
                .'.md';

            File::put($outputFile, $markdown);

            $this->comment(
                sprintf(
                    'Saved %d files to quick_%s.md (%d dependencies)',
                    $count,
                    $folderName,
                    count($dependencies)
                )
            );
        }

        return self::SUCCESS;
    }
}
