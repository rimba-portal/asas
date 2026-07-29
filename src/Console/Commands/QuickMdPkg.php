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
    /**
     * Execute the console command.
     */
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
            $markdown .= '*Generated: '.now()->toDateTimeString()."*\n\n";

            $count = 0;

            foreach ($files as $file) {

                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $count++;

                $contents = File::get($file->getRealPath());

                $markdown .= "## {$file->getRelativePathname()}\n\n";
                $markdown .= "```php\n";
                $markdown .= $contents;
                $markdown .= "\n```\n\n";
            }

            $outputFile = $rootPath.DIRECTORY_SEPARATOR.$folderName.'_quick.md';

            File::put($outputFile, $markdown);

            $this->comment(
                sprintf('Saved %d files to %s_quick.md', $count, $folderName)
            );
        }

        return self::SUCCESS;
    }
}
