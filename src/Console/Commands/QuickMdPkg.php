<?php

declare(strict_types=1);

namespace Bites\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Read and display all PHP files recursively and save them to quick.md')]
#[Signature('bites:quick-md 
                            {folder : The path to the folder} 
                            {--flat : Disable deep folder scanning (check top-level only)} 
                            {--s|search= : Optional keyword filter}')]
class QuickMdPkg extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $folder = $this->argument('folder');
        $flat = $this->option('flat');
        $searchKeyword = $this->option('search');

        // Resolve path context
        $path = base_path($folder);
        if (! File::isDirectory($path)) {
            $path = $folder;
        }

        if (! File::isDirectory($path)) {
            $this->error('The directory does not exist: '.$path);

            return Command::FAILURE;
        }

        // Get matching file set
        $files = $flat ? File::files($path) : File::allFiles($path);
        $processedCount = 0;

        // Initialize Markdown content with a dynamic header block
        $markdownContent = "# PHP Files Code Dump\n";
        $markdownContent .= '*Generated on: '.now()->toDateTimeString()."*\n";
        $markdownContent .= "*Target Folder: `{$path}`*\n\n---\n\n";

        foreach ($files as $file) {
            // Skip the output file itself if it already exists to avoid infinite self-reading loops
            if ($file->getFilename() === 'quick.md') {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $contents = File::get($filePath);

            if ($searchKeyword && ! str_contains($contents, $searchKeyword)) {
                continue;
            }

            $processedCount++;
            $relativePath = $file->getRelativePathname();

            // 1. Output content live to the command console window
            $this->info('========================================');
            $this->warn('FILE: '.$relativePath);
            $this->info('PATH: '.$filePath);
            $this->info('========================================');
            $this->line($contents);
            $this->line("\n");

            // 2. Append markdown-wrapped block segments
            $markdownContent .= "## File: `{$relativePath}`\n";
            $markdownContent .= "**Absolute Path:** `{$filePath}`\n\n";
            $markdownContent .= "```php\n";
            $markdownContent .= $contents."\n";
            $markdownContent .= "```\n\n";
            $markdownContent .= "---\n\n";
        }

        // Write or completely replace the 'quick.md' file inside the targeted folder
        $markdownFilePath = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'quick.md';
        File::put($markdownFilePath, $markdownContent);

        $this->comment('Process complete.');
        $this->comment(sprintf('Read %d file(s). Saved markdown summary to: %s', $processedCount, $markdownFilePath));

        return Command::SUCCESS;
    }
}
