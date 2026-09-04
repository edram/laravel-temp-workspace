<?php

namespace Edram\LaravelPackage\Commands;

use Illuminate\Console\Command;

class LaravelPackageCommand extends Command
{
    public $signature = 'laravel-package';

    public $description = 'Execute the package command';

    public function handle(): int
    {
        $this->components->info('Package command executed.');

        return self::SUCCESS;
    }
}
