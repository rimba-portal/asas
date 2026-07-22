<?php

declare(strict_types=1);

namespace Bites\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

#[Description('Inspect all PHP classes in app/ and packages/ and overwrite a compact classes.md file')]
#[Signature('bites:class-members')]
class GetClassMembers extends Command
{
    public function handle(): int
    {
        $targetFolders = ['app', 'packages'];
        $outputPath = base_path('classes.md');

        $markdownContent = "# Class Directory Blueprint\n";
        $markdownContent .= '*Generated automatically on '.now()->toDateTimeString()."*\n\n";
        $markdownContent .= "'<span style='color:#9CDCFE'> 𝔁 : property </span>  \n";
        $markdownContent .= "'<span style='color:#DCDCAA'> λ : class method  </span>  \n";
        $markdownContent .= "---\n\n";

        $classCount = 0;

        foreach ($targetFolders as $targetFolder) {
            $fullPath = base_path($targetFolder);

            if (! File::isDirectory($fullPath)) {
                $this->warn(sprintf('Directory [%s] not found. Skipping...', $targetFolder));

                continue;
            }

            $this->info(sprintf('Scanning directory: [%s]...', $targetFolder));
            $files = File::allFiles($fullPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->extractFullyQualifiedClassName($file->getRealPath());

                if (! $className || (! class_exists($className) && ! interface_exists($className) && ! trait_exists($className))) {
                    continue;
                }

                $markdownContent .= $this->generateClassMarkdown($className);
                $classCount++;
            }
        }

        File::put($outputPath, $markdownContent);

        $this->components->info(sprintf('Success! Inspected %d structures. Document refreshed at: [%s]', $classCount, $outputPath));

        return Command::SUCCESS;
    }

    protected function generateClassMarkdown(string $className): string
    {
        try {
            $reflectionClass = new ReflectionClass($className);
            $md = "## 📦 Class: `{$className}`\n";

            // 1. Structural Overview Metadata
            $relativePath = str_replace(base_path().'/', '', $reflectionClass->getFileName());
            $md .= "- **Location:** `{$relativePath}` (Line {$reflectionClass->getStartLine()})  \n";

            $lineage = [];
            $currentReflection = $reflectionClass;
            while ($parent = $currentReflection->getParentClass()) {
                $lineage[] = '`'.$parent->getName().'`';
                $currentReflection = $parent;
            }

            if ($lineage !== []) {
                $md .= '- **Extends:** '.implode(' ➔ ', $lineage)."  \n";
            }

            $interfaces = $reflectionClass->getInterfaceNames();
            if (! empty($interfaces)) {
                $formattedInterfaces = array_map(fn (string $i): string => sprintf('`%s`', $i), $interfaces);
                $md .= '- **Implements:** '.implode(",  \n", $formattedInterfaces)."  \n";
            }

            $traits = array_keys($reflectionClass->getTraits());
            if ($traits !== []) {
                $formattedTraits = array_map(fn (string $t): string => sprintf('`%s`', $t), $traits);
                $md .= '- **Uses Traits:** '.implode(', ', $formattedTraits)."  \n";
            }

            // 2. DocBlocks
            $docComment = $reflectionClass->getDocComment();
            if ($docComment) {
                // $md .= "\n### 📄 Documentation\n```php\n".trim(\$docComment)."\n```\n";
            }

            // 3. Extract and filter members
            $properties = $reflectionClass->getProperties();
            $allMethods = $reflectionClass->getMethods();
            $methods = array_values(array_filter($allMethods, fn (\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === $className));

            // Compact Line-by-Line Properties List
            // $md .= "\n### ⚙️ Properties\n";
            if (empty($properties)) {
                // $md .= "*No defined properties.*\n";
            } else {
                foreach ($properties as $property) {
                    $modifiers = implode(' ', \Reflection::getModifierNames($property->getModifiers())) ?: 'public';
                    $type = $property->hasType() ? $this->formatType($property->getType()) : 'mixed';
                    $md .= sprintf("𝔁 %s %s <span style='color:#9CDCFE'>$%s</span>  \n", $modifiers, $type, $property->getName());
                }
            }

            // Compact Line-by-Line Methods List
            // $md .= "\n### 🛠️ Methods\n";
            if ($methods === []) {
                // $md .= "*No local methods.*\n";
            } else {
                foreach ($methods as $method) {
                    $modifiers = implode(' ', \Reflection::getModifierNames($method->getModifiers())) ?: 'public';
                    $returnType = $method->hasReturnType() ? $this->formatType($method->getReturnType()) : 'void/mixed';
                    $md .= sprintf("λ %s <span style='color:#DCDCAA'>$%s</span> : %s  \n", $modifiers, $method->getName(), $returnType);
                }
            }

            return $md."\n---\n\n";
        } catch (\Exception $exception) {
            return "## ❌ Error: `{$className}`\n*Failed to run reflection engine: {$exception->getMessage()}*\n\n---\n\n";
        }
    }

    /**
     * Safely parse basic, union, and intersection types to strings.
     */
    protected function formatType($type): string
    {
        if (! $type) {
            return 'mixed';
        }

        if ($type instanceof ReflectionUnionType) {
            $types = array_map(function (ReflectionIntersectionType|ReflectionNamedType $t) {
                return method_exists($t, 'getName') ? $t->getName() : (string) $t;
            }, $type->getTypes());

            return implode('|', $types);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $types = array_map(function (\ReflectionType $t) {
                return method_exists($t, 'getName') ? $t->getName() : (string) $t;
            }, $type->getTypes());

            return implode('&', $types);
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return method_exists($type, 'getName') ? $type->getName() : (string) $type;
    }

    protected function extractFullyQualifiedClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/(?:class|interface|trait)\s+(\w+)/', $contents, $matches)) {
            $class = trim($matches[1]);
        }

        return $class !== '' && $class !== '0' ? ($namespace !== '' && $namespace !== '0' ? $namespace.'\\'.$class : $class) : null;
    }
}
