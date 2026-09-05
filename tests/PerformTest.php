<?php

use Edram\LaravelTempWorkspace\TempWorkspace;
use Edram\LaravelTempWorkspace\Tests\Fixtures\PerformingWorkspace;
use Edram\LaravelTempWorkspace\Tests\Fixtures\WorkspaceService;
use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Support\Facades\Exceptions;

it('performs a named workspace with storage, dependencies and a stable id', function () {
    $workspace = new PerformingWorkspace('document');
    $id = $workspace->id();

    $result = app(WorkspaceManager::class)->perform($workspace);

    expect($result)->toBe('processed: document')
        ->and($workspace->id())->toBe($id)->not->toBeEmpty()
        ->and($workspace->get('input.txt'))->toBe('document');
});

it('performs an anonymous workspace with container dependencies', function () {
    $result = app(WorkspaceManager::class)->perform(
        function (TempWorkspace $workspace, WorkspaceService $service): string {
            $workspace->put('input.txt', 'document');

            return $service->process($workspace->get('input.txt'));
        },
    );

    expect($result)->toBe('processed: document');
});

it('rejects reusing a workspace and terminates its resources once', function () {
    $workspace = new class extends TempWorkspace
    {
        public int $terminations = 0;

        public bool $fileExistedWhenTerminated = false;

        public function handle(): void
        {
            $this->put('input.txt', 'document');
        }

        public function terminate(): void
        {
            $this->terminations++;
            $this->fileExistedWhenTerminated = $this->exists('input.txt');
        }
    };

    $manager = app(WorkspaceManager::class);
    $manager->perform($workspace);
    $disk = $workspace->disk();

    expect(fn () => $manager->perform($workspace))
        ->toThrow(LogicException::class, 'The temporary workspace has already been prepared.');

    $manager->cleanup();
    $manager->cleanup();

    expect(fn () => $manager->perform($workspace))
        ->toThrow(LogicException::class, 'The temporary workspace has already been prepared.');

    expect($workspace->terminations)->toBe(1)
        ->and($workspace->fileExistedWhenTerminated)->toBeTrue()
        ->and($disk->directoryMissing(''))->toBeTrue();
});

it('reports termination errors while finishing every hook and workspace cleanup', function () {
    Exceptions::fake();
    $manager = app(WorkspaceManager::class);
    $otherStorage = $manager->create()->disk();
    $workspace = new class extends TempWorkspace
    {
        public function handle(): void {}

        public function terminate(WorkspaceService $service): never
        {
            throw new LogicException($service->process('termination failed'));
        }
    };
    $manager->perform($workspace);
    $workspace->put('input.txt', 'document');
    $callbackResult = null;

    $workspace->terminating(fn () => throw new RuntimeException('Callback failed.'));
    $workspace->terminating(
        function (TempWorkspace $current, WorkspaceService $service) use (&$callbackResult): void {
            $callbackResult = $service->process($current->get('input.txt'));
        },
    );

    $manager->cleanup();

    expect($callbackResult)->toBe('processed: document')
        ->and($workspace->disk()->directoryMissing(''))->toBeTrue()
        ->and($otherStorage->directoryMissing(''))->toBeTrue();

    Exceptions::assertReported(LogicException::class);
    Exceptions::assertReported(RuntimeException::class);
    Exceptions::assertReportedCount(2);
});

it('preserves the work exception and reports failure hook errors', function (bool $hookFails) {
    Exceptions::fake();
    $workspace = new class($hookFails) extends TempWorkspace
    {
        public ?string $failure = null;

        public function __construct(private readonly bool $hookFails) {}

        public function handle(): never
        {
            throw new RuntimeException('Work failed.');
        }

        public function failed(Throwable $exception): void
        {
            $this->failure = $exception->getMessage();

            if ($this->hookFails) {
                throw new LogicException('Failure hook failed.');
            }
        }
    };

    $manager = app(WorkspaceManager::class);

    expect(fn () => $manager->perform($workspace))
        ->toThrow(RuntimeException::class, 'Work failed.')
        ->and($workspace->failure)->toBe('Work failed.');

    if ($hookFails) {
        Exceptions::assertReported(LogicException::class);
    } else {
        Exceptions::assertNothingReported();
    }
})->with([false, true]);
