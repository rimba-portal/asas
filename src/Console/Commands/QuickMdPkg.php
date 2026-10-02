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

            foreach ($files as $file) {

                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $count++;

                $contents = File::get($file->getRealPath());

                preg_match_all(
                    '/^use\s+([^;]+);/mi',
                    $contents,
                    $matches
                );

                foreach ($matches[1] as $class) {

                    $class = trim($class);

                    // Exclude framework dependencies
                    if (
                        str_starts_with($class, 'Illuminate\\') ||
                        str_starts_with($class, 'Filament\\')
                    ) {
                        continue;
                    }

                    // Convert class to package root
                    $parts = explode('\\', $class);

                    if (count($parts) < 2) {
                        continue;
                    }

                    $dependencies[] = $parts[0].'\\'.$parts[1];
                }

                $markdown .= "## {$file->getRelativePathname()}\n\n";
                $markdown .= "```php\n";
                $markdown .= $contents;
                $markdown .= "\n```\n\n";
            }

            $dependencies = array_values(
                array_unique($dependencies)
            );

            sort($dependencies);

            $markdown .= "\n---\n\n";
            $markdown .= "# Dependencies\n\n";
            $markdown .= "```php\n";
            $markdown .= var_export($dependencies, true);
            $markdown .= "\n```\n";

            $outputFile = $rootPath.DIRECTORY_SEPARATOR.'quick_'.$folderName.'.md';

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
