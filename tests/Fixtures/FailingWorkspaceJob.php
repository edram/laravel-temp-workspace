<?php

namespace Edram\LaravelTempWorkspace\Tests\Fixtures;

use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class FailingWorkspaceJob implements ShouldQueue
{
    use Queueable;

    public static ?FilesystemAdapter $storage = null;

    public static ?FilesystemAdapter $failureStorage = null;

    public static bool $fileExistedWhenFailed = false;

    public function handle(WorkspaceManager $manager): void
    {
        self::$storage = $manager->create()->disk();
        self::$storage->put('input.txt', 'workspace contents');

        throw new RuntimeException('Job failed.');
    }

    public function failed(?Throwable $exception): void
    {
        self::$fileExistedWhenFailed = self::$storage->exists('input.txt');
        self::$failureStorage = app(WorkspaceManager::class)->create()->disk();
        self::$failureStorage->put('failure.txt', $exception->getMessage());
    }
}
