<?php

namespace Edram\LaravelTempWorkspace;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use LogicException;
use Throwable;

class WorkspaceManager
{
    /** @var list<list<TempWorkspace>> Nested command and job lifecycles own only the workspaces they create. */
    private array $scopes = [[]];

    public function __construct(
        private readonly Container $container,
        private readonly FilesystemManager $filesystems,
        private readonly ?string $disk,
        private readonly string $directory,
    ) {}

    /** Perform named or anonymous work synchronously and return its result. */
    public function perform(TempWorkspace|Closure $work): mixed
    {
        if ($work instanceof Closure) {
            $workspace = $this->create();

            return $this->container->call($work, [TempWorkspace::class => $workspace]);
        }

        $this->prepare($work);

        try {
            return $this->container->call([$work, 'handle']);
        } catch (Throwable $exception) {
            if (method_exists($work, 'failed')) {
                rescue(fn () => $work->failed($exception));
            }

            throw $exception;
        }
    }

    public function create(): TempWorkspace
    {
        return $this->prepare(new TempWorkspace);
    }

    private function prepare(TempWorkspace $workspace): TempWorkspace
    {
        $id = $workspace->id();
        $directory = trim($this->directory, '/');
        $prefix = $directory === '' ? $id : $directory.'/'.$id;

        $disk = $this->filesystems->disk($this->disk);

        if (! $disk instanceof FilesystemAdapter) {
            throw new LogicException("Disk [{$this->disk}] does not expose a Laravel filesystem adapter.");
        }

        $storage = $this->filesystems->build([
            'driver' => 'scoped',
            // Storage::fake() supplies a local disk configuration without a driver.
            'disk' => ['driver' => 'local', ...$disk->getConfig()],
            'prefix' => $prefix,
            'throw' => true,
        ]);

        if (! $storage instanceof FilesystemAdapter) {
            throw new LogicException('The scoped disk does not expose a Laravel filesystem adapter.');
        }

        $workspace->initialize($storage);
        $storage->makeDirectory('');

        $scope = array_key_last($this->scopes);
        $this->scopes[$scope][] = $workspace;

        return $workspace;
    }

    public function beginScope(): void
    {
        $this->scopes[] = [];
    }

    public function endScope(): void
    {
        $workspaces = count($this->scopes) > 1
            ? array_pop($this->scopes)
            : array_splice($this->scopes[0], 0);

        $this->cleanupWorkspaces($workspaces);
    }

    public function cleanup(): void
    {
        while (count($this->scopes) > 1) {
            $this->cleanupWorkspaces(array_pop($this->scopes));
        }

        $this->cleanupWorkspaces(array_splice($this->scopes[0], 0));
    }

    /** @param list<TempWorkspace> $workspaces */
    private function cleanupWorkspaces(array $workspaces): void
    {
        while ($workspace = array_pop($workspaces)) {
            if (method_exists($workspace, 'terminate')) {
                rescue(fn () => $this->container->call([$workspace, 'terminate']));
            }

            $workspace->invokeTerminatingCallbacks($this->container);

            rescue(fn () => $workspace->destroy());
        }
    }
}
