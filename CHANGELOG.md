# Changelog

## Unreleased

### Added

- Isolated temporary workspaces backed by Laravel Storage.
- Named and anonymous workspace execution through Laravel's container.
- Stable workspace IDs and direct `handle` return values.
- `failed`, `terminate`, and container-invoked `terminating` lifecycle hooks.
- Automatic cleanup after HTTP requests, Artisan commands, and queue jobs.
- A `destroy()` method to explicitly delete an individual workspace directory.

### Changed

- Use Laravel's scoped Storage disks, preserving parent prefixes and driver capabilities.
- Require Laravel 12.45+ or 13.x for queue attempt lifecycle events.
- Reject repeated preparation of the same workspace instance.
- Follow Laravel's default filesystem disk unless a workspace disk is specified.
- Use `TEMP_WORKSPACE_DISK` and `TEMP_WORKSPACE_DIRECTORY` environment variables.

### Fixed

- Preserve numeric workspace directory names such as `0`.
- Clean queue workspaces after failure handlers have finished.
- Preserve work exceptions when failure hooks throw, and report cleanup errors.
