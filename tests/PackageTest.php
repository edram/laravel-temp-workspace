<?php

use Edram\LaravelPackage\Facades\LaravelPackage as LaravelPackageFacade;
use Edram\LaravelPackage\LaravelPackage;

it('can resolve the package class from the container', function () {
    expect(app(LaravelPackage::class)->echoPhrase('Hello'))->toBe('Hello');
});

it('can use the facade', function () {
    expect(LaravelPackageFacade::echoPhrase('Hello'))->toBe('Hello');
});

it('merges package config', function () {
    expect(config('laravel-package'))->toBeArray();
});

it('registers the package command', function () {
    $this->artisan('laravel-package')
        ->expectsOutputToContain('Package command executed.')
        ->assertSuccessful();
});
