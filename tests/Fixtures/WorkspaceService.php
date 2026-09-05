<?php

namespace Edram\LaravelTempWorkspace\Tests\Fixtures;

class WorkspaceService
{
    public function process(string $value): string
    {
        return "processed: {$value}";
    }
}
