# Repository Guidelines

## Project Structure & Module Organization

This repository contains the `edram/laravel-temp-workspace` Laravel package.

- `src/` contains the workspace, manager, service provider, and facade.
- `config/laravel-temp-workspace.php` contains publishable disk and directory configuration.
- `tests/` contains Pest tests backed by Orchestra Testbench.
- `.github/workflows/` defines CI for tests, static analysis, and formatting.

## Build, Test, and Development Commands

Run these from the repository root:

```bash
composer install
composer prepare
composer test
composer analyse
composer format
composer validate --strict
```

- `composer prepare` runs Testbench package discovery.
- `composer test` runs the Pest test suite.
- `composer analyse` runs PHPStan/Larastan.
- `composer format` runs Laravel Pint.
- `composer validate --strict` validates `composer.json`.

## Coding Style & Naming Conventions

When writing or editing code, use the `coding` and `tdd` skills.

Follow Laravel PHP style conventions with 4-space indentation. Use typed methods and properties where practical. Package classes live under `Edram\LaravelTempWorkspace`.

Naming patterns:

- Service provider: `LaravelTempWorkspaceServiceProvider`
- Facade: `Facades\LaravelTempWorkspace`
- Manager: `WorkspaceManager`
- Config key and publish tags: `laravel-temp-workspace`

## Testing Guidelines

Use Pest for tests and Orchestra Testbench for Laravel integration behavior. Add or update tests in `tests/` when changing workspaces, storage integration, lifecycle cleanup, the service provider, facade, or configuration.

Prefer behavior-focused test names:

```php
it('cleans a workspace when a queue job fails', function () {
    //
});
```

Run `composer test` and `composer analyse` before opening a pull request.

## Commit & Pull Request Guidelines

When committing code, use the `git-commit-convention` skill.

Pull requests should include:

- A concise summary of the change.
- Any related issue or motivation.
- Notes about public API, configuration, storage, or lifecycle changes.
- Confirmation that `composer test`, `composer analyse`, and `composer format` pass.

## Security & Configuration Tips

Do not commit local secrets, tokens, or generated credentials. Keep `composer.lock` only if this repository is used as an application-style template; omit it for normal published package releases if desired.
