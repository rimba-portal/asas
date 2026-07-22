<?php

declare(strict_types=1);

namespace Rimba\Base;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Command;
use ReflectionClass;


class BaseServiceProvider extends ServiceProvider
{
    protected string $configFile = __DIR__ . '/../config/bites.php';

    protected function bootPackage(): void
    {
        if ($this->app->runningInConsole()) { $this->registerCommandsFromDirectory(); }

    }
    protected function registerPackage(): void
    {
        //
    }
    /**
     * Dynamically discover and boot all commands inside the package directory.
     */
    protected function registerCommandsFromDirectory()
    {
        $commandDir = __DIR__ . '/Console/Commands';
        if (! is_dir($commandDir)) {
            return;
        }
        $commands = [];
        foreach (glob($commandDir . '/*.php') as $file) {
            $className = basename($file, '.php');
            $class = 'Rimba\\Base\\Console\\Commands\\' . $className;
            if (class_exists($class) && is_subclass_of($class, Command::class)) {
                $reflection = new ReflectionClass($class);
                if (! $reflection->isAbstract()) {
                    $commands[] = $class;
                }
            }
        }
        if ($commands !== []) {
            $this->commands($commands);
        }
    }

}
