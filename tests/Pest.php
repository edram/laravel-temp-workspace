<?php

use Edram\LaravelTempWorkspace\Tests\TestCase;
use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class)
    ->beforeEach(function () {
        Storage::fake('workspaces');
        config()->set('laravel-temp-workspace.disk', 'workspaces');
    })
    ->afterEach(function () {
        app(WorkspaceManager::class)->cleanup();
    })
    ->in(__DIR__);
