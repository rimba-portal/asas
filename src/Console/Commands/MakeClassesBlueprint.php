<?php

declare(strict_types=1);

namespace Rimba\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

#[Description('Generate compact PHP class blueprint for AI / Copilot context')]
#[Signature('rimba:blue-print')]
class MakeClassesBlueprint extends Command
{
    protected array $targetFolders = [
        'app',
        'packages',
        'rimba',
    ];

    protected string $outputFile = 'classes.md';

    protected string $classUseOutputFile = 'class_use.md';

    public function handle(): int
    {
        $outputPath = base_path($this->outputFile);
        $classUseOutputPath = base_path($this->classUseOutputFile);

        /*
         * ------------------------------------------------------------
         * Class blueprint
         * ------------------------------------------------------------
         */

        $content = "# PHP Class Blueprint\n";
        $content .= '*Generated automatically on '.now()->toDateTimeString()."*\n\n";

        $content .= "## Legend\n\n";
        $content .= "- `namespace` = Namespace\n";
        $content .= "- `#` = PHP Attribute\n";
        $content .= "- `class` = Class declaration\n";
        $content .= "- `interface` = Interface declaration\n";
        $content .= "- `trait` = Trait declaration\n";
        $content .= "- `enum` = Enum declaration\n";
        $content .= "- `&` = Trait use\n";
        $content .= "- `=` = Constant\n";
        $content .= "- `@+` = Public property\n";
        $content .= "- `@*` = Protected property\n";
        $content .= "- `@-` = Private property\n";
        $content .= "- `$+` = Public method\n";
        $content .= "- `$*` = Protected method\n";
        $content .= "- `$-` = Private method\n\n";

        $content .= "---\n\n";

        /*
         * ------------------------------------------------------------
         * Class dependencies
         * ------------------------------------------------------------
         */

        $classUses = "# PHP Class Dependencies\n";
        $classUses .= '*Generated automatically on '.now()->toDateTimeString()."*\n\n";

        $classUses .= "| Class | Namespace | Use |\n";
        $classUses .= "|---|---|---|\n";

        $fileCount = 0;
        $useCount = 0;

        foreach ($this->targetFolders as $targetFolder) {
            $fullPath = base_path($targetFolder);

            if (! File::isDirectory($fullPath)) {
                $this->warn(sprintf(
                    'Directory [%s] not found. Skipping...',
                    $targetFolder
                ));

                continue;
            }

            $this->info(sprintf('Scanning [%s]...', $targetFolder));

            foreach (File::allFiles($fullPath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $blueprint = $this->generateFileBlueprint($file);

                if ($blueprint === null) {
                    continue;
                }

                $content .= $blueprint;
                $content .= "\n---\n\n";

                foreach ($this->extractFileUses($file) as $use) {
                    $classUses .= sprintf(
                        "| %s | %s | %s |\n",
                        $use['class'],
                        $use['namespace'],
                        $use['use'],
                    );

                    $useCount++;
                }

                $fileCount++;
            }
        }

        File::put($outputPath, $content);
        File::put($classUseOutputPath, $classUses);

        $this->components->info(sprintf(
            'Success! Generated blueprint for %d PHP files at [%s]',
            $fileCount,
            $outputPath
        ));

        $this->components->info(sprintf(
            'Generated %d class dependencies at [%s]',
            $useCount,
            $classUseOutputPath
        ));

        return Command::SUCCESS;
    }

    protected function generateFileBlueprint(SplFileInfo $file): ?string
    {
        $path = $file->getRealPath();

        if (! is_string($path)) {
            return null;
        }

        $source = file_get_contents($path);

        if (! is_string($source) || trim($source) === '') {
            return null;
        }

        $source = $this->normalizeNewLines($source);

        $namespace = $this->extractNamespaceLine($source);
        $classLike = $this->extractClassLikeLine($source);

        if ($namespace === null && $classLike === null) {
            return null;
        }

        $relativePath = str_replace(
            base_path().DIRECTORY_SEPARATOR,
            '',
            $path
        );

        $relativePath = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            $relativePath
        );

        $classAttributes = $this->extractAttributesBeforeClass($source);
        $body = $this->extractClassBody($source);

        $traits = $body === null
            ? []
            : $this->extractTraitUses($body);

        $constants = $body === null
            ? []
            : $this->extractConstants($body);

        $properties = $body === null
            ? []
            : $this->extractProperties($body);

        $methods = $body === null
            ? []
            : $this->extractMethods($body);

        $md = "[file] {$relativePath}\n\n";

        if ($namespace !== null) {
            $md .= $namespace."\n";
        }

        foreach ($classAttributes as $attribute) {
            $md .= '# '.$attribute."\n";
        }

        if ($classAttributes !== []) {
            $md .= "\n";
        }

        if ($classLike !== null) {
            $md .= $classLike."\n";
        }

        foreach ($traits as $trait) {
            $md .= '& '.$trait."\n";
        }

        if ($traits !== []) {
            $md .= "\n";
        }

        foreach ($constants as $constant) {
            foreach ($constant['attributes'] as $attribute) {
                $md .= '# '.$attribute."\n";
            }

            $md .= '= '.$constant['line']."\n";
        }

        if ($constants !== []) {
            $md .= "\n";
        }

        foreach ($properties as $property) {
            foreach ($property['attributes'] as $attribute) {
                $md .= '# '.$attribute."\n";
            }

            $md .= $property['line']."\n";
        }

        if ($properties !== []) {
            $md .= "\n";
        }

        foreach ($methods as $method) {
            foreach ($method['attributes'] as $attribute) {
                $md .= '# '.$attribute."\n";
            }

            $md .= $method['line']."\n";
        }

        return rtrim($md)."\n";
    }

    /**
     * Extract file-level imports.
     *
     * Trait `use` statements inside the class body are intentionally
     * excluded because those are already handled by extractTraitUses().
     */
    protected function extractFileUses(SplFileInfo $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path)) {
            return [];
        }

        $source = file_get_contents($path);

        if (! is_string($source) || trim($source) === '') {
            return [];
        }

        $source = $this->normalizeNewLines($source);

        /*
         * Namespace
         */
        if (
            preg_match(
                '/^\s*namespace\s+([^;]+);/m',
                $source,
                $namespaceMatches
            ) !== 1
        ) {
            return [];
        }

        $namespace = trim($namespaceMatches[1]);

        /*
         * Class / interface / trait / enum name
         */
        if (
            preg_match(
                '/\b(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/',
                $source,
                $classMatches,
                PREG_OFFSET_CAPTURE
            ) !== 1
        ) {
            return [];
        }

        $class = $classMatches[1][0];
        $classPosition = $classMatches[0][1];

        /*
         * Only inspect everything before the class declaration.
         *
         * This prevents class-level trait usage such as:
         *
         * use HasFactory;
         *
         * from appearing as an imported dependency.
         */
        $header = substr(
            $source,
            0,
            $classPosition
        );

        if (! is_string($header)) {
            return [];
        }

        $dependencies = [];

        if (
            preg_match_all(
                '/^\s*use\s+([^;]+);/m',
                $header,
                $useMatches
            ) === false
        ) {
            return [];
        }

        foreach ($useMatches[1] as $use) {
            $use = $this->normalizeSpaces($use);

            if ($use === '') {
                continue;
            }

            $dependencies[] = [
                'class' => $class,
                'namespace' => $namespace,
                'use' => $use,
            ];
        }

        return $dependencies;
    }

    protected function extractNamespaceLine(string $source): ?string
    {
        if (
            preg_match(
                '/^\s*namespace\s+[^;]+;/m',
                $source,
                $matches
            ) !== 1
        ) {
            return null;
        }

        return trim($matches[0]);
    }

    protected function extractClassLikeLine(string $source): ?string
    {
        $lines = explode("\n", $source);
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#[')) {
                continue;
            }

            if (
                preg_match(
                    '/^(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+/i',
                    $line
                ) !== 1
            ) {
                continue;
            }

            $declaration = $line;

            while (
                ! str_contains($declaration, '{')
                && ! str_ends_with(trim($declaration), ';')
                && $i + 1 < $count
            ) {
                $i++;

                $declaration .= ' '.trim($lines[$i]);
            }

            $declaration = preg_replace(
                '/\s*\{.*$/',
                '',
                $declaration
            );

            $declaration = rtrim(
                (string) $declaration,
                ';'
            );

            return $this->normalizeSpaces($declaration);
        }

        return null;
    }

    protected function extractAttributesBeforeClass(string $source): array
    {
        $lines = explode("\n", $source);

        $attributes = [];
        $buffer = '';
        $insideAttribute = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (
                preg_match(
                    '/^(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+/i',
                    $trimmed
                ) === 1
            ) {
                break;
            }

            if (str_starts_with($trimmed, '#[')) {
                $insideAttribute = true;
                $buffer = $trimmed;

                if ($this->attributeIsClosed($buffer)) {
                    $attributes[] = $this->normalizeSpaces($buffer);

                    $buffer = '';
                    $insideAttribute = false;
                }

                continue;
            }

            if ($insideAttribute) {
                $buffer .= ' '.$trimmed;

                if ($this->attributeIsClosed($buffer)) {
                    $attributes[] = $this->normalizeSpaces($buffer);

                    $buffer = '';
                    $insideAttribute = false;
                }
            }
        }

        return array_values(
            array_unique($attributes)
        );
    }

    protected function extractClassBody(string $source): ?string
    {
        if (
            preg_match(
                '/\b(class|interface|trait|enum)\s+[A-Za-z_][A-Za-z0-9_]*.*\{/i',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE
            )
        ) {
            $startPos = $matches[0][1]
                + strlen($matches[0][0])
                - 1;

            $length = strlen($source);
            $braceCount = 0;
            $bodyStart = $startPos + 1;

            for ($i = $startPos; $i < $length; $i++) {
                if ($source[$i] === '{') {
                    $braceCount++;
                } elseif ($source[$i] === '}') {
                    $braceCount--;

                    if ($braceCount === 0) {
                        return substr(
                            $source,
                            $bodyStart,
                            $i - $bodyStart
                        );
                    }
                }
            }
        }

        return null;
    }

    protected function extractTraitUses(string $body): array
    {
        $traits = [];

        if (
            preg_match_all(
                '/^\s*use\s+([^;]+);/m',
                $body,
                $matches
            )
        ) {
            foreach ($matches[1] as $match) {
                foreach (explode(',', $match) as $trait) {
                    $traits[] = $this->normalizeSpaces(
                        trim($trait)
                    );
                }
            }
        }

        return $traits;
    }

    protected function extractConstants(string $body): array
    {
        $constants = [];

        $lines = explode("\n", $body);
        $currentAttributes = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#[')) {
                $currentAttributes[] = $this->normalizeSpaces(
                    $trimmed
                );

                continue;
            }

            if (
                preg_match(
                    '/^(?:public|protected|private)?\s*const\s+([A-Z_][A-Z0-9_]*)\s*=/i',
                    $trimmed,
                    $matches
                )
            ) {
                $cleanLine = preg_replace(
                    '/;.*$/',
                    '',
                    $trimmed
                );

                $constants[] = [
                    'attributes' => $currentAttributes,
                    'line' => $this->normalizeSpaces($cleanLine),
                ];

                $currentAttributes = [];
            }
        }

        return $constants;
    }

    protected function extractProperties(string $body): array
    {
        $properties = [];

        $lines = explode("\n", $body);
        $currentAttributes = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#[')) {
                $currentAttributes[] = $this->normalizeSpaces(
                    $trimmed
                );

                continue;
            }

            /*
             * Exclude methods and constants.
             */
            if (
                str_contains($trimmed, 'function')
                || str_contains($trimmed, 'const')
            ) {
                continue;
            }

            if (
                preg_match(
                    '/^(public|protected|private|\b)\s*(?:readonly\s+)?(?:[\w\\\|]+)?\s*(\$[\w]+)/i',
                    $trimmed,
                    $matches
                )
            ) {
                $visibility = $matches[1] ?: 'public';

                $prefix = match ($visibility) {
                    'protected' => '@*',
                    'private' => '@-',
                    default => '@+',
                };

                $cleanLine = preg_replace(
                    '/;.*$/',
                    '',
                    $trimmed
                );

                $properties[] = [
                    'attributes' => $currentAttributes,
                    'line' => $prefix.' '.$this->normalizeSpaces($cleanLine),
                ];

                $currentAttributes = [];
            }
        }

        return $properties;
    }

    protected function extractMethods(string $body): array
    {
        $methods = [];

        $lines = explode("\n", $body);
        $currentAttributes = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#[')) {
                $currentAttributes[] = $this->normalizeSpaces(
                    $trimmed
                );

                continue;
            }

            if (
                preg_match(
                    '/^(?:(public|protected|private)\s+)?(?:static\s+)?function\s+([\w_]+)\s*\((.*)\)/i',
                    $trimmed,
                    $matches
                )
            ) {
                $visibility = $matches[1] ?: 'public';

                $prefix = match ($visibility) {
                    'protected' => '$*',
                    'private' => '$-',
                    default => '$+',
                };

                $cleanLine = preg_replace(
                    '/\s*\{.*$/',
                    '',
                    $trimmed
                );

                $cleanLine = rtrim(
                    (string) $cleanLine,
                    ';'
                );

                $methods[] = [
                    'attributes' => $currentAttributes,
                    'line' => $prefix.' '.$this->normalizeSpaces($cleanLine),
                ];

                $currentAttributes = [];
            }
        }

        return $methods;
    }

    protected function normalizeNewLines(string $str): string
    {
        return str_replace(
            ["\r\n", "\r"],
            "\n",
            $str
        );
    }

    protected function normalizeSpaces(string $str): string
    {
        return trim(
            (string) preg_replace(
                '/\s+/',
                ' ',
                $str
            )
        );
    }

    protected function attributeIsClosed(string $str): bool
    {
        return str_ends_with(
            $str,
            ']'
        );
    }
}
