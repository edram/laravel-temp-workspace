# Laravel Temp Workspace

**English** · [简体中文](README.zh-CN.md)

Temporary, isolated workspaces backed by Laravel Storage, with automatic cleanup
at the end of a request, command, or queue job attempt.

- Use familiar Storage methods: `put()`, `get()`, `path()`, `readStream()`, and more.
- Define a named workspace or pass a closure, with Laravel container injection.
- Return your result directly from `handle()` or the closure.
- Release additional resources through lifecycle hooks before files are deleted.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Basic usage](#basic-usage)
- [Usage](#usage)
  - [Named workspaces](#named-workspaces)
  - [Anonymous workspaces](#anonymous-workspaces)
  - [Temporary directory only](#temporary-directory-only)
- [Storage and configuration](#storage-and-configuration)
- [Lifecycle and cleanup](#lifecycle-and-cleanup)
  - [Named hooks](#named-hooks)
  - [Terminating callbacks](#terminating-callbacks)
  - [Manual cleanup](#manual-cleanup)
- [API reference](#api-reference)
- [Development](#development)
- [License](#license)

## Requirements

| Dependency | Supported versions |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12.45+ within 12.x, or 13.x |

## Installation

```bash
composer require edram/laravel-temp-workspace
```

Laravel discovers the service provider automatically. Configuration is optional;
workspaces use Laravel's default filesystem disk and the `temporary-workspaces`
directory.

## Basic usage

Use `create()` to get an isolated temporary directory and work with it through
Laravel Storage methods:

```php
use Edram\LaravelTempWorkspace\Facades\LaravelTempWorkspace;

$workspace = LaravelTempWorkspace::create();
$workspace->put('hello.txt', 'Hello, workspace!');

$contents = $workspace->get('hello.txt');

// Optionally delete the workspace directory and its contents early.
// $workspace->destroy();
```

Manual deletion is optional. At the end of the request, command, or queue job
attempt, the package automatically removes the workspace directory and its
contents.

## Usage

| Approach | Use it when |
| --- | --- |
| Named workspace | The work is reusable and has its own input or lifecycle hooks. |
| Anonymous workspace | A closure is enough for a small, one-off operation. |
| Temporary directory | You want to manage the work yourself and use temporary storage. |

### Named workspaces

Create a class in `app/TempWorkspaces`. Put input values in the constructor and
declare service dependencies on `handle()`, like a Laravel job or command.

For example, download a file and return its MD5 checksum:

```php
namespace App\TempWorkspaces;

use Edram\LaravelTempWorkspace\TempWorkspace;
use Illuminate\Http\Client\Factory as Http;

final class DownloadFile extends TempWorkspace
{
    public function __construct(
        public readonly string $url,
    ) {}

    public function handle(Http $http): string
    {
        $filename = 'download.bin';

        $this->put(
            $filename,
            $http->timeout(60)->get($this->url)->throw()->body(),
        );

        return $this->checksum($filename, ['checksum_algo' => 'md5']);
    }
}
```

Perform it through the manager:

```php
use App\TempWorkspaces\DownloadFile;
use Edram\LaravelTempWorkspace\WorkspaceManager;

$workspace = new DownloadFile($url);
$id = $workspace->id();

$md5 = app(WorkspaceManager::class)->perform($workspace);
```

The `handle()` return value is the result. Each instance has a stable ID that can
be read before execution, and can only be prepared once. Create a new instance
for each execution or retry.

### Anonymous workspaces

Closures can receive both the current workspace and other container dependencies:

```php
use Edram\LaravelTempWorkspace\Facades\LaravelTempWorkspace;
use Edram\LaravelTempWorkspace\TempWorkspace;
use Illuminate\Http\Client\Factory as Http;

$md5 = LaravelTempWorkspace::perform(
    function (TempWorkspace $workspace, Http $http) use ($url): string {
        $workspace->put(
            'download.bin',
            $http->timeout(60)->get($url)->throw()->body(),
        );

        return $workspace->checksum('download.bin', ['checksum_algo' => 'md5']);
    },
);
```

### Temporary directory only

Use `create()` when you want to perform file operations directly:

```php
use Edram\LaravelTempWorkspace\WorkspaceManager;

$workspace = app(WorkspaceManager::class)->create();
$workspace->put('input.txt', 'Workspace contents');

$contents = $workspace->get('input.txt');
$path = $workspace->path('input.txt');
```

Automatic cleanup also applies to workspaces created this way.

## Storage and configuration

Optionally publish `config/laravel-temp-workspace.php`:

```bash
php artisan vendor:publish --tag=laravel-temp-workspace-config
```

The disk defaults to `null`, so Laravel Storage resolves `filesystems.default`
(configured by Laravel's `FILESYSTEM_DISK`). Set a workspace disk only when it
should differ from the application's default.

| Config key | Environment variable | Default |
| --- | --- | --- |
| `disk` | `TEMP_WORKSPACE_DISK` | `null` — follow `filesystems.default` |
| `directory` | `TEMP_WORKSPACE_DIRECTORY` | `temporary-workspaces` |

Optional environment overrides:

```dotenv
# TEMP_WORKSPACE_DISK=s3
TEMP_WORKSPACE_DIRECTORY=temporary-workspaces
```

Leave `TEMP_WORKSPACE_DISK` unset to follow Laravel's default disk. `directory`
is relative to the disk; it is not an absolute filesystem path.

Each workspace is stored at `temporary-workspaces/{id}` relative to the selected
disk. Workspace disks use Laravel's scoped driver and preserve the parent disk's
prefix and options. `Storage::fake()` is supported for testing.

`TempWorkspace` forwards filesystem methods to its isolated disk. Access the
underlying Laravel `FilesystemAdapter` through `$workspace->disk()`.

On a local disk, `path()` returns a local filesystem path. On a remote disk, it
returns a storage path; use `get()`, `readStream()`, or `checksum()` to process the
file through Storage. URL methods depend on the selected driver's capabilities.

**Preserve outputs before cleanup.** If your result refers to a temporary file,
copy or stream it to permanent storage before the workspace's lifecycle ends.

## Lifecycle and cleanup

| Context | Cleanup happens |
| --- | --- |
| HTTP request | During application termination, after the response is sent. |
| Artisan command | When the command finishes, including ordinary exceptions. |
| Queue job | After each attempt, including the job's failure handler. |

Nested command and job scopes clean their own workspaces, preserving those owned
by the enclosing lifecycle. `perform()` itself does not trigger cleanup.

Cleanup relies on Laravel reaching its lifecycle events. Forced process
termination and worker timeouts may leave files behind.

### Named hooks

Add these methods to a named workspace as needed:

```php
public function failed(\Throwable $exception): void
{
    report($exception);
}

public function terminate(\Psr\Log\LoggerInterface $logger): void
{
    $logger->info('Workspace terminated.', ['workspace_id' => $this->id()]);
}
```

`failed()` receives the exception thrown by `handle()`. `terminate()` runs at
cleanup before the directory is deleted, so it can still access the files and
release additional resources. Dependencies on `terminate()` are container-resolved.

### Terminating callbacks

Register additional cleanup from within your work or on a created workspace:

```php
use Edram\LaravelTempWorkspace\TempWorkspace;
use Psr\Log\LoggerInterface;

$workspace->terminating(function (TempWorkspace $current, LoggerInterface $logger): void {
    $logger->info('Cleaning temporary files.', ['workspace_id' => $current->id()]);
});
```

Callbacks run through the container after `terminate()` and before directory
deletion. A failing hook does not prevent remaining hooks or cleanup from running.
Hook and directory cleanup errors are reported through Laravel's exception
handler. If `failed()` throws, the original work exception is still rethrown.

### Manual cleanup

Call `$workspace->destroy()` to delete a single workspace's directory and contents
immediately. It returns a boolean and only deletes files; lifecycle hooks still
run when the manager cleans up, so those hooks should not rely on deleted files.

For long-running work, consume or persist the results, then explicitly clean all
workspaces currently tracked by the manager:

```php
use Edram\LaravelTempWorkspace\WorkspaceManager;

app(WorkspaceManager::class)->cleanup();
```

Cleanup affects every scope tracked by that manager. Repeated calls without new
workspaces do not rerun the completed cleanup hooks.

## API reference

| API | Purpose |
| --- | --- |
| `$manager->create()` | Create and prepare a temporary workspace. |
| `$manager->perform($work)` | Synchronously execute a workspace instance or closure and return its result. |
| `$manager->cleanup()` | Clean every workspace currently tracked by the manager. |
| `$workspace->id()` | Get the stable ID of this instance. |
| `$workspace->disk()` | Access its prepared Laravel filesystem adapter. |
| `$workspace->destroy()` | Delete this workspace's directory and contents immediately. |
| `$workspace->terminating($callback)` | Register a callback to run before directory deletion. |
| `$workspace->put()`, `get()`, `path()`, … | Call the corresponding Laravel filesystem method. |

Resolve `WorkspaceManager` through the container, or call `create()`, `perform()`,
and `cleanup()` through the `LaravelTempWorkspace` facade.

## Development

```bash
composer install
composer test
composer analyse
composer format
composer validate --strict
```

Tests use Pest and Orchestra Testbench, including HTTP, Artisan, and synchronous
and database queue lifecycle coverage.

## License

MIT. See [LICENSE.md](LICENSE.md).
