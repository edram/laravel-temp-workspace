<?php

namespace Edram\LaravelTempWorkspace;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemAdapter;
use LogicException;

/**
 * A temporary, isolated Laravel filesystem prepared by the workspace manager.
 *
 * @mixin FilesystemAdapter
 */
class TempWorkspace
{
    /** @var list<Closure> */
    private array $terminatingCallbacks = [];

    private ?string $id = null;

    private ?FilesystemAdapter $disk = null;

    /** @internal Prepare once so the workspace belongs to a single lifecycle scope. */
    final public function initialize(FilesystemAdapter $disk): void
    {
        if ($this->disk !== null) {
            throw new LogicException('The temporary workspace has already been prepared.');
        }

        $this->disk = $disk;
    }

    /** Get the stable identity that is independent of the runtime filesystem. */
    public function id(): string
    {
        return $this->id ??= bin2hex(random_bytes(16));
    }

    public function disk(): FilesystemAdapter
    {
        return $this->disk ?? throw new LogicException('The temporary workspace has not been prepared.');
    }

    /** Delete this workspace's directory and contents without invoking lifecycle hooks. */
    public function destroy(): bool
    {
        return $this->disk()->deleteDirectory('');
    }

    /** Register work that must run before the temporary directory is deleted. */
    public function terminating(callable $callback): static
    {
        $this->terminatingCallbacks[] = Closure::fromCallable($callback);

        return $this;
    }

    /** @internal */
    final public function invokeTerminatingCallbacks(Container $container): void
    {
        foreach ($this->terminatingCallbacks as $callback) {
            rescue(fn () => $container->call($callback, [self::class => $this]));
        }
    }

    /** @param array<int|string, mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->{$method}(...$parameters);
    }
}
