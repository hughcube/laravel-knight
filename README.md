<h1><p align="center"> Laravel Knight </p></h1>

<p align="center">
    <a href="https://github.com/hughcube/laravel-knight/actions/workflows/test.yaml">
        <img src="https://github.com/hughcube/laravel-knight/actions/workflows/test.yaml/badge.svg" alt="Test Actions status">
    </a>
    <a href="https://github.com/hughcube/laravel-knight/actions/workflows/lint.yaml">
        <img src="https://github.com/hughcube/laravel-knight/actions/workflows/lint.yaml/badge.svg" alt="Lint Actions status">
    </a>
    <a href="https://styleci.io/repos/373508253">
        <img src="https://github.styleci.io/repos/373508253/shield?branch=master" alt="StyleCI">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-knight">
        <img src="https://img.shields.io/packagist/v/hughcube/laravel-knight.svg?style=flat-square" alt="Latest Version on Packagist">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-knight">
        <img src="https://img.shields.io/packagist/dt/hughcube/laravel-knight.svg?style=flat-square" alt="Total Downloads">
    </a>
    <a href="https://packagist.org/packages/hughcube/laravel-knight">
        <img src="https://img.shields.io/packagist/php-v/hughcube/laravel-knight.svg?style=flat-square" alt="PHP Version Require">
    </a>
    <a href="https://github.com/hughcube/laravel-knight/blob/master/LICENSE">
        <img src="https://img.shields.io/github/license/hughcube/laravel-knight.svg?style=flat-square" alt="License">
    </a>
</p>

## Introduction

Laravel Knight is a powerful helper extension for the [Laravel PHP framework](https://github.com/laravel/framework), designed to provide a more robust and efficient development experience. It offers enhanced Eloquent models, query builders, controller utilities, caching mechanisms, optimistic locking, middleware collections, and PostgreSQL array support.

## Requirements

- PHP 7.4+
- Laravel 6.0+

## Features

- **Enhanced Eloquent Models**: Advanced caching mechanisms, optimistic locking, soft deletes, and convenient data manipulation methods
- **Optimized Query Builder**: Extended with `whereLike`, `whereRange`, primary key caching queries, and more
- **PostgreSQL Array Support**: Native PostgreSQL array type handling with `whereIntArrayContains`, `whereTextArrayOverlaps`, etc.
- **Middleware Collection**: Authentication, environment guards, IP restrictions, CORS, HSTS, request logging
- **Controller Utilities**: Action trait with parameter validation, caching, and event dispatching
- **OPcache Management**: Commands for compiling, clearing, and monitoring OPcache
- **Queue Job Tools**: Built-in jobs for ping checks, file cleanup, cache GC, and WeChat token refresh
- **Mixin Extensions**: Extended Collection, Carbon, Str, and Query Builder classes

## Installing

```shell
composer require hughcube/laravel-knight -vvv
```

## Usage

### 1. Model Enhancements

Extend `HughCube\Laravel\Knight\Database\Eloquent\Model` to get caching and other utilities.

```php
<?php

namespace App\Models;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use Psr\SimpleCache\CacheInterface;
use Illuminate\Support\Facades\Cache;

class User extends Model
{
    public function getCache(): ?CacheInterface
    {
        return Cache::store('redis');
    }
}

// Usage
$user = User::findById(1);              // Fetch with cache
$users = User::findByIds([1, 2, 3]);    // Batch fetch with cache
$user = User::noCacheQuery()->find(1);  // Bypass cache
```

### 2. Optimistic Locking

Add optimistic locking to prevent data conflicts in concurrent scenarios.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\OptimisticLock;

class Product extends Model
{
    use OptimisticLock;
}

// Migration: $table->unsignedBigInteger('data_version')->default(1);

// Usage
$product = Product::find(1);
$product->price = 100.50;

try {
    $product->save(); // Auto handles optimistic locking
} catch (\HughCube\Laravel\Knight\Exceptions\OptimisticLockException $e) {
    // Handle conflict
}

// Disable lock temporarily
$product->disableOptimisticLock()->save();
```

### 3. Query Builder Extensions

```php
// LIKE queries
Order::query()->whereLike('name', '%keyword%')->get();
Order::query()->whereEscapeLike('name', 'keyword')->get();      // Escaped contains
Order::query()->whereEscapeLeftLike('name', 'prefix')->get();   // Escaped prefix

// Range query
Order::query()->whereRange('created_at', ['2023-01-01', '2023-12-31'])->get();

// Available (not soft-deleted) query
Order::availableQuery()->get();
```

### 4. PostgreSQL Array Queries

```php
// Integer array queries
User::query()->whereIntArrayContains('role_ids', [1, 2])->get();
User::query()->whereIntArrayOverlaps('tag_ids', [3, 4])->get();

// Text array queries
Post::query()->whereTextArrayContains('tags', ['php', 'laravel'])->get();

// Array length/empty checks
User::query()->whereArrayLength('permissions', '>', 0)->get();
User::query()->whereArrayIsNotEmpty('roles')->get();
```

### 5. Middleware

```php
// routes/web.php
Route::middleware(['knight.only-local'])->group(function () {
    // Local access only
});

Route::middleware(['knight.only-private-ip'])->group(function () {
    // Private IP only
});

Route::middleware(['knight.https'])->group(function () {
    // Force HTTPS
});
```

Available middleware:
- `knight.authenticate` - Authentication
- `knight.only-local` / `knight.only-local-env` - Local restrictions
- `knight.only-prod-env` / `knight.only-test-env` - Environment restrictions
- `knight.only-private-ip` / `knight.only-public-ip` - IP restrictions
- `knight.https` - Force HTTPS
- `knight.hsts` - HSTS header
- `knight.log-request` - Request logging
- `knight.cors` - CORS handling

### 6. Controller Action Trait

```php
<?php

namespace App\Http\Controllers;

use HughCube\Laravel\Knight\Routing\Controller;

class ProductController extends Controller
{
    public function show(int $id)
    {
        return $this->getOrSet(
            $this->getActionCacheKey(__METHOD__, ['id' => $id]),
            fn() => Product::find($id),
            60
        );
    }
}
```

### 7. OPcache Commands

```shell
# Compile files to OPcache
php artisan opcache:compile

# Clear CLI OPcache
php artisan opcache:clear-cli

# Create preload script
php artisan opcache:create-preload
```

### 8. Built-in Queue Jobs

```php
use HughCube\Laravel\Knight\Queue\Jobs\PingJob;
use HughCube\Laravel\Knight\Queue\Jobs\CleanFilesJob;
use HughCube\Laravel\Knight\Queue\Jobs\CacheTableGcJob;

// Dispatch jobs
PingJob::dispatch();
CleanFilesJob::dispatch('/path/to/clean', '*.log', 7);
CacheTableGcJob::dispatch();
```

## Configuration

Publish the configuration file:

```shell
php artisan vendor:publish --provider="HughCube\Laravel\Knight\ServiceProvider"
```

Configure route prefixes in `config/knight.php`:

```php
return [
    'opcache' => [
        'route_prefix' => false,  // Set to enable OPcache routes, false to disable
    ],
    'request' => [
        'route_prefix' => false,  // Request log routes
    ],
    'ping' => [
        'route_prefix' => null,   // Ping routes
    ],
    'phpinfo' => [
        'route_prefix' => false,  // PHPInfo routes
    ],
    'devops' => [
        'route_prefix' => false,  // Devops system info routes
    ],
];
```

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/hughcube/laravel-knight/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/hughcube/laravel-knight/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

Laravel Knight is open-sourced software licensed under the [MIT license](LICENSE).
