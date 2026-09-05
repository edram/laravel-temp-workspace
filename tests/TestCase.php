<?php

namespace Edram\LaravelTempWorkspace\Tests;

use Edram\LaravelTempWorkspace\LaravelTempWorkspaceServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelTempWorkspaceServiceProvider::class,
        ];
    }

    protected function processJob(ShouldQueue $job, string $driver): void
    {
        if ($driver === 'sync') {
            dispatch_sync($job);

            return;
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $queue = Queue::connection('database');
        $queue->push($job);
        $queuedJob = $queue->pop();
        $this->assertNotNull($queuedJob);

        app('queue.worker')->process('database', $queuedJob, new WorkerOptions(maxTries: 1));
    }
}
