<?php

namespace Edram\LaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string echoPhrase(string $phrase)
 *
 * @see \Edram\LaravelPackage\LaravelPackage
 */
class LaravelPackage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Edram\LaravelPackage\LaravelPackage::class;
    }
}
