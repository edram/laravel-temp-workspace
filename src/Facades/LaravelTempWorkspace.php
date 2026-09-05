<?php

namespace Edram\LaravelTempWorkspace\Facades;

use Closure;
use Edram\LaravelTempWorkspace\TempWorkspace;
use Edram\LaravelTempWorkspace\WorkspaceManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TempWorkspace create()
 * @method static mixed perform(TempWorkspace|Closure $work)
 * @method static void cleanup()
 *
 * @see WorkspaceManager
 */
class LaravelTempWorkspace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkspaceManager::class;
    }
}
