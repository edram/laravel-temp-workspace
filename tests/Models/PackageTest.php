<?php

use Edram\LaravelPackage\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a package', function () {
    Package::create();

    expect(Package::all())->toHaveCount(1);
});
