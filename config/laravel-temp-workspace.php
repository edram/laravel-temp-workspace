<?php

return [
    // Null uses Laravel's default filesystem disk.
    'disk' => env('TEMP_WORKSPACE_DISK'),

    // Relative to the selected disk's root and any existing prefix.
    'directory' => env('TEMP_WORKSPACE_DIRECTORY', 'temporary-workspaces'),
];
