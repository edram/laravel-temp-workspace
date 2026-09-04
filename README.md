# Laravel Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/edram/laravel-package.svg?style=flat-square)](https://packagist.org/packages/edram/laravel-package)
[![Tests](https://github.com/edram/laravel-package/actions/workflows/run-tests.yml/badge.svg)](https://github.com/edram/laravel-package/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/edram/laravel-package.svg?style=flat-square)](https://packagist.org/packages/edram/laravel-package)

A reusable Laravel package foundation for building Edram Laravel extensions.

## Installation

Install the package via Composer:

```bash
composer require edram/laravel-package
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-package-config"
```

Publish the migrations:

```bash
php artisan vendor:publish --tag="laravel-package-migrations"
php artisan migrate
```

## Usage

Resolve the package from the container:

```php
use Edram\LaravelPackage\LaravelPackage;

$laravelPackage = app(LaravelPackage::class);

echo $laravelPackage->echoPhrase('Hello, Laravel!');
```

Use the facade:

```php
use Edram\LaravelPackage\Facades\LaravelPackage;

echo LaravelPackage::echoPhrase('Hello, Laravel!');
```

Run the package command:

```bash
php artisan laravel-package
```

## Development

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format the code:

```bash
composer format
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
