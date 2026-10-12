<?php

declare(strict_types=1);

namespace Rimba\Base\Services;

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;
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

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    public function register(): void
    {
        $this->registerConfig();
        $this->registerViewPath();
        $this->registerIconPath();

        $this->registerPackage();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    public function boot(): void
    {
        $this->ensureStorageFilesExist();

        $this->registerViews();
        $this->registerIcons();

        $this->bootPackage();
    }

    /*
    |--------------------------------------------------------------------------
    | Package Hooks
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Package Storage
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure files supplied by the package exist in the application's
     * storage/app tree.
     *
     * Expected package structure:
     *
     * storage/
     * ├── private/
     * │   ├── help/
     * │   ├── api/
     * │   └── seed/
     * │       └── pending/
     * │
     * └── public/
     *     ├── branding/
     *     └── images/
     *
     * Existing application files are never overwritten.
     */
    protected function ensureStorageFilesExist(): void
    {
        $source = $this->packagePath('storage');
        if (! File::isDirectory($source)) {
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $relativePath = $file->getRelativePathname();
            $target = storage_path(
                $relativePath
            );
            if (File::exists($target)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getRealPath(), $target);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    protected function registerConfig(): void
    {
        if ($this->configFile === '' || ! file_exists($this->configFile)) {
            return;
        }

        $packageConfig = require $this->configFile;
        if (! is_array($packageConfig)) {
            return;
        }

        config([$this->configName => array_replace_recursive(config($this->configName, []), $packageConfig)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    */

    protected function registerViewPath(): void
    {
        if ($this->viewsPath === '' || ! is_dir($this->viewsPath)) {
            return;
        }

        app()->instance(
            'bites.views',
            $this->appendUniquePath($this->getViewPaths(), $this->viewsPath)
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

    protected function getViewPaths(): array
    {
        if (! app()->bound('bites.views')) {
            return [];
        }

        return app('bites.views');
    }

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    */

    protected function registerIconPath(): void
    {
        if ($this->iconsPath === '' || ! is_dir($this->iconsPath)) {
            return;
        }

        app()->instance('bites.icons', $this->appendUniquePath($this->getIconPaths(), $this->iconsPath));
    }

    protected function registerIcons(): void
    {
        if ($this->getIconPaths() === []) {
            return;
        }

        $this->callAfterResolving(Factory::class, function (Factory $factory): void {
            $this->addIconSet($factory);
        });
    }

    protected function addIconSet(Factory $factory): void
    {
        if (app()->bound('bites.icons.registered')) {
            return;
        }

        app()->instance('bites.icons.registered', true);
        $factory->add(
            $this->iconSet,
            [
                'paths' => $this->getIconPaths(),
                'prefix' => $this->iconPrefix,
            ]
        );
    }

    protected function getIconPaths(): array
    {
        if (! app()->bound('bites.icons')) {
            return [];
        }

        return app('bites.icons');
    }

    /*
    |--------------------------------------------------------------------------
    | Package Utilities
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a path relative to the child package.
     */
    protected function packagePath(string $path = ''): string
    {
        $providerFile = (new ReflectionClass($this))->getFileName();
        $packageRoot = dirname($providerFile).'/..';

        return $path === '' ? $packageRoot : $packageRoot.'/'.$path;
    }

    /**
     * Append a path without introducing duplicates.
     */
    protected function appendUniquePath(array $paths, string $path): array
    {
        $paths[] = $path;

        return array_values(array_unique($paths));
    }
}
