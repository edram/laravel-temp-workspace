<?php

namespace Edram\LaravelPackage;

use Edram\LaravelPackage\Commands\LaravelPackageCommand;
use Illuminate\Support\ServiceProvider;

class LaravelPackageServiceProvider extends ServiceProvider
{
    protected string $package = 'laravel-package';

    /** @var array<class-string> */
    protected array $commands = [
        LaravelPackageCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/laravel-package.php'), $this->package);

        $this->app->singleton(LaravelPackage::class, fn () => new LaravelPackage);
        $this->app->alias(LaravelPackage::class, $this->package);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            $this->packagePath('config/laravel-package.php') => config_path("{$this->package}.php"),
        ], "{$this->package}-config");

        $this->publishes([
            $this->packagePath('resources/views') => resource_path("views/vendor/{$this->package}"),
        ], "{$this->package}-views");

        $this->publishes([
            $this->packagePath('database/migrations') => database_path('migrations'),
        ], "{$this->package}-migrations");
    }

    protected function registerRoutes(): void
    {
        if (file_exists($routes = $this->packagePath('routes/web.php'))) {
            $this->loadRoutesFrom($routes);
        }
    }

    protected function registerViews(): void
    {
        if (is_dir($views = $this->packagePath('resources/views'))) {
            $this->loadViewsFrom($views, $this->package);
        }
    }

    protected function registerMigrations(): void
    {
        if (is_dir($migrations = $this->packagePath('database/migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    protected function packagePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }
}
