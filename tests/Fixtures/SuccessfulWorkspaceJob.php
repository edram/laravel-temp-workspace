<?php

namespace Edram\LaravelTempWorkspace\Tests\Fixtures;

use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Queue\Queueable;

class SuccessfulWorkspaceJob implements ShouldQueue
{
    use Queueable;

    public static ?FilesystemAdapter $storage = null;

    public function handle(WorkspaceManager $manager): void
    {
        self::$storage = $manager->create()->disk();
        self::$storage->put('input.txt', 'workspace contents');
    }
}
