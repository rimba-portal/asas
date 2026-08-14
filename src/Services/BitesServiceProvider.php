<?php

declare(strict_types=1);

namespace Rimba\Base\Services;

use BladeUI\Icons\Factory;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class BitesServiceProvider extends ServiceProvider
{
    /**
     * Package config file.
     */
    protected string $configFile = __DIR__.'/../config/bites.php';

    /**
     * Package views path.
     */
    protected string $viewsPath = __DIR__.'/../resources/views';

    /**
     * Package icons path.
     */
    protected string $iconsPath = __DIR__.'/../resources/svg';

    /**
     * Shared config key.
     */
    protected string $configName = 'bites';

    /**
     * Shared view namespace.
     */
    protected string $viewNamespace = 'bites';

    /**
     * Shared Blade icon set name.
     */
    protected string $iconSet = 'bites';

    /**
     * Shared Blade icon prefix.
     */
    protected string $iconPrefix = 'bites';

    public function register(): void
    {
        $this->registerConfig();
        $this->registerViewPath();
        $this->registerIconPath();

        $this->registerPackage();
    }

    public function boot(): void
    {
        $this->registerViews();
        $this->registerIcons();
        $this->registerHelpFiles(); // <-- Hook added here

        $this->bootPackage();
    }

    /**
     * Override this in child providers for package-specific bindings.
     */
    protected function registerPackage(): void
    {
        //
    }

    /**
     * Override this in child providers for package-specific boot logic.
     */
    protected function bootPackage(): void
    {
        //
    }

    /**
     * Automatically register help files for publishing if the directory exists.
     */
    protected function registerHelpFiles(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Get the directory of the actual child class, not this abstract class
        $childDir = dirname((new ReflectionClass($this))->getFileName());
        $helpPath = $childDir.'/../help';

        if (! is_dir($helpPath)) {
            return;
        }

        // Publishes package/help/* content directly into public/helpfiles/
        // Uses the child class's base name as a tag modifier for granular control
        $tagName = 'bites-help-'.strtolower((new ReflectionClass($this))->getShortName());

        $this->publishes([
            $helpPath => public_path('helpfiles'),
        ], ['bites-helpfiles', $tagName]);
    }

    protected function registerConfig(): void
    {
        if (
            $this->configFile === ''
            || ! file_exists($this->configFile)
        ) {
            return;
        }

        $packageConfig = require $this->configFile;

        if (! is_array($packageConfig)) {
            return;
        }

        config([
            $this->configName => array_replace_recursive(
                config($this->configName, []),
                $packageConfig,
            ),
        ]);
    }

    protected function registerViewPath(): void
    {
        if (
            $this->viewsPath === ''
            || ! is_dir($this->viewsPath)
        ) {
            return;
        }

        app()->instance(
            'bites.views',
            $this->appendUniquePath(
                $this->getViewPaths(),
                $this->viewsPath,
            )
        );
    }

    protected function registerIconPath(): void
    {
        if (
            $this->iconsPath === ''
            || ! is_dir($this->iconsPath)
        ) {
            return;
        }

        app()->instance(
            'bites.icons',
            $this->appendUniquePath(
                $this->getIconPaths(),
                $this->iconsPath,
            )
        );
    }

    protected function registerViews(): void
    {
        if (app()->bound('bites.views.registered')) {
            return;
        }

        app()->instance('bites.views.registered', true);

        foreach ($this->getViewPaths() as $path) {
            $this->loadViewsFrom($path, $this->viewNamespace);
        }
    }

    protected function registerIcons(): void
    {
        if ($this->getIconPaths() === []) {
            return;
        }

        $this->callAfterResolving(
            Factory::class,
            function (Factory $factory): void {
                $this->addIconSet($factory);
            }
        );
    }

    protected function addIconSet(Factory $factory): void
    {
        if (app()->bound('bites.icons.registered')) {
            return;
        }

        app()->instance('bites.icons.registered', true);

        $factory->add($this->iconSet, [
            'paths' => $this->getIconPaths(),
            'prefix' => $this->iconPrefix,
        ]);
    }

    protected function getViewPaths(): array
    {
        if (! app()->bound('bites.views')) {
            return [];
        }

        return app('bites.views');
    }

    protected function getIconPaths(): array
    {
        if (! app()->bound('bites.icons')) {
            return [];
        }

        return app('bites.icons');
    }

    protected function appendUniquePath(array $paths, string $path): array
    {
        $paths[] = $path;

        return array_values(array_unique($paths));
    }
}
