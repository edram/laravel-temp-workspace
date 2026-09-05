<?php

namespace Edram\LaravelTempWorkspace;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;

class LaravelTempWorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/config/laravel-temp-workspace.php',
            'laravel-temp-workspace',
        );

        $this->app->scoped(WorkspaceManager::class, function ($app): WorkspaceManager {
            return new WorkspaceManager(
                $app,
                $app->make(FilesystemManager::class),
                $app['config']->get('laravel-temp-workspace.disk'),
                (string) $app['config']->get('laravel-temp-workspace.directory'),
            );
        });
    }

    public function boot(Dispatcher $events): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__).'/config/laravel-temp-workspace.php' => $this->app->configPath('laravel-temp-workspace.php'),
            ], 'laravel-temp-workspace-config');
        }

        $events->listen([CommandStarting::class, JobProcessing::class], function (): void {
            $this->app->make(WorkspaceManager::class)->beginScope();
        });

        // JobAttempted runs after failure handlers in both the worker and sync queue.
        $events->listen([CommandFinished::class, JobAttempted::class], function (): void {
            $this->app->make(WorkspaceManager::class)->endScope();
        });

        $this->app->terminating(fn () => $this->app->make(WorkspaceManager::class)->cleanup());
    }
}
