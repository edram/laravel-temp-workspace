<?php

namespace Edram\LaravelTempWorkspace\Tests\Fixtures;

use Edram\LaravelTempWorkspace\TempWorkspace;

class PerformingWorkspace extends TempWorkspace
{
    public function __construct(private readonly string $value) {}

    public function handle(WorkspaceService $service): string
    {
        $this->put('input.txt', $this->value);

        return $service->process($this->get('input.txt'));
    }
}
