<?php

use Edram\LaravelTempWorkspace\Facades\LaravelTempWorkspace;
use Edram\LaravelTempWorkspace\Tests\Fixtures\FailingWorkspaceJob;
use Edram\LaravelTempWorkspace\Tests\Fixtures\SuccessfulWorkspaceJob;
use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

it('creates an isolated workspace backed by Laravel storage', function () {
    $workspace = LaravelTempWorkspace::create();
    $workspace->put('input.txt', 'workspace contents');

    $otherWorkspace = app(WorkspaceManager::class)->create();

    expect($workspace->get('input.txt'))->toBe('workspace contents')
        ->and($otherWorkspace->missing('input.txt'))->toBeTrue()
        ->and($otherWorkspace->id())->not->toBe($workspace->id());

    LaravelTempWorkspace::cleanup();

    expect($workspace->directoryMissing(''))->toBeTrue()
        ->and($otherWorkspace->directoryMissing(''))->toBeTrue();
});

it('destroys only the selected workspace and allows later lifecycle cleanup', function () {
    $manager = app(WorkspaceManager::class);
    $workspace = $manager->create();
    $workspace->put('nested/input.txt', 'temporary contents');
    $otherWorkspace = $manager->create();
    $otherWorkspace->put('keep.txt', 'other contents');

    expect($workspace->destroy())->toBeTrue()
        ->and($workspace->directoryMissing(''))->toBeTrue()
        ->and($otherWorkspace->get('keep.txt'))->toBe('other contents')
        ->and($workspace->destroy())->toBeTrue();

    $manager->cleanup();

    expect($otherWorkspace->directoryMissing(''))->toBeTrue();
});

it('uses the application default disk unless the workspace disk is overridden', function (?string $disk) {
    Storage::fake('workspace-override');
    config()->set('laravel-temp-workspace', require dirname(__DIR__).'/config/laravel-temp-workspace.php');
    config()->set('filesystems.default', 'workspaces');

    if ($disk !== null) {
        config()->set('laravel-temp-workspace.disk', $disk);
    }

    $workspace = app(WorkspaceManager::class)->create();
    $workspace->put('input.txt', 'workspace contents');
    $path = 'temporary-workspaces/'.$workspace->id().'/input.txt';

    expect(Storage::disk($disk ?? 'workspaces')->get($path))->toBe('workspace contents');
})->with([
    'application default' => null,
    'workspace override' => 'workspace-override',
]);

it('preserves the configured directory, disk prefix and Laravel file paths', function (string $directory, string $prefix) {
    config()->set('laravel-temp-workspace.directory', $directory);
    $disk = Storage::build([
        'driver' => 'scoped',
        'disk' => ['driver' => 'local', ...Storage::disk('workspaces')->getConfig()],
        'prefix' => 'tenants/alice',
    ]);
    Storage::set('workspaces', $disk);
    $disk->put('keep.txt', 'permanent contents');
    $manager = app(WorkspaceManager::class);
    $workspace = $manager->create();
    $workspace->put('input.txt', 'workspace contents');
    $workspacePath = $prefix.$workspace->id();
    $path = $workspacePath.'/input.txt';

    expect($disk->get($path))->toBe('workspace contents')
        ->and($workspace->path('input.txt'))->toBe($disk->path($path))
        ->and($workspace->url('input.txt'))->toBe($disk->url($path));

    $manager->cleanup();

    expect($disk->directoryMissing($workspacePath))->toBeTrue()
        ->and($disk->get('keep.txt'))->toBe('permanent contents');
})->with([
    'trimmed directory' => ['/temporary-workspaces/', 'temporary-workspaces/'],
    'numeric directory' => ['0', '0/'],
    'disk root' => ['', ''],
]);

it('does not retain workspace manager state across Laravel scopes', function () {
    $manager = app(WorkspaceManager::class);

    app()->forgetScopedInstances();

    expect(app(WorkspaceManager::class))->not->toBe($manager);
});

it('reports storage cleanup failures and continues cleaning other workspaces', function () {
    Exceptions::fake();
    $manager = app(WorkspaceManager::class);
    $otherStorage = $manager->create()->disk();
    $disk = Storage::disk('workspaces');
    Storage::extend('undeletable', function ($app, array $config): FilesystemAdapter {
        $local = Storage::createLocalDriver($config);

        return new class($local->getDriver(), $local->getAdapter(), $config) extends FilesystemAdapter
        {
            public function deleteDirectory($directory): bool
            {
                throw new RuntimeException('Storage cleanup failed.');
            }
        };
    });
    Storage::set('workspaces', Storage::build(['driver' => 'undeletable', ...$disk->getConfig()]));
    $workspace = $manager->create();

    $manager->cleanup();

    try {
        Exceptions::assertReported(RuntimeException::class);
        expect($otherStorage->directoryMissing(''))->toBeTrue()
            ->and($workspace->directoryExists(''))->toBeTrue();
    } finally {
        $disk->deleteDirectory('temporary-workspaces/'.$workspace->id());
    }
});

it('cleans request workspaces after response handling', function (bool $fails) {
    $storage = null;

    Route::get('/workspace', function () use (&$storage, $fails): string {
        $storage = app(WorkspaceManager::class)->create()->disk();
        $storage->put('input.txt', 'workspace contents');

        if ($fails) {
            throw new RuntimeException('Request failed.');
        }

        return 'done';
    });

    $kernel = app(Kernel::class);
    $request = Request::create('/workspace');
    $response = $kernel->handle($request);

    expect($response->getStatusCode())->toBe($fails ? 500 : 200)
        ->and($storage->directoryExists(''))->toBeTrue();

    $kernel->terminate($request, $response);

    expect($storage->directoryMissing(''))->toBeTrue();
})->with([false, true]);

it('cleans command workspaces and preserves the enclosing workspace', function (bool $fails) {
    app(ConsoleKernel::class)->rerouteSymfonyCommandEvents();
    $parent = app(WorkspaceManager::class)->create();
    $storage = null;
    Artisan::command('workspace:test', function () use (&$storage, $fails): void {
        $storage = app(WorkspaceManager::class)->create()->disk();

        if ($fails) {
            throw new RuntimeException('Command failed.');
        }
    });

    if ($fails) {
        expect(fn () => Artisan::call('workspace:test'))->toThrow(RuntimeException::class, 'Command failed.');
    } else {
        expect(Artisan::call('workspace:test'))->toBe(0);
    }

    expect($storage->directoryMissing(''))->toBeTrue()
        ->and($parent->directoryExists(''))->toBeTrue();
})->with([false, true]);

it('cleans a failed job after its failure handler and preserves the enclosing workspace', function (string $driver) {
    FailingWorkspaceJob::$storage = null;
    FailingWorkspaceJob::$failureStorage = null;
    FailingWorkspaceJob::$fileExistedWhenFailed = false;
    $parent = app(WorkspaceManager::class)->create();
    $parent->put('parent.txt', 'request contents');

    expect(fn () => $this->processJob(new FailingWorkspaceJob, $driver))
        ->toThrow(RuntimeException::class, 'Job failed.')
        ->and(FailingWorkspaceJob::$fileExistedWhenFailed)->toBeTrue()
        ->and(FailingWorkspaceJob::$storage?->directoryMissing(''))->toBeTrue()
        ->and(FailingWorkspaceJob::$failureStorage?->directoryMissing(''))->toBeTrue()
        ->and($parent->get('parent.txt'))->toBe('request contents');
})->with(['sync', 'database']);

it('cleans a successful job and preserves the enclosing workspace', function (string $driver) {
    SuccessfulWorkspaceJob::$storage = null;
    $parent = app(WorkspaceManager::class)->create();
    $parent->put('parent.txt', 'request contents');

    $this->processJob(new SuccessfulWorkspaceJob, $driver);

    expect(SuccessfulWorkspaceJob::$storage?->directoryMissing(''))->toBeTrue()
        ->and($parent->get('parent.txt'))->toBe('request contents');
})->with(['sync', 'database']);
