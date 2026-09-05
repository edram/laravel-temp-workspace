# Laravel Temp Workspace

[English](README.md) · **简体中文**

基于 Laravel Storage 的临时工作区，为文件操作提供独立目录，并在请求、命令或队列任务的一次执行结束后自动清理。

- 直接使用熟悉的 Storage 方法：`put()`、`get()`、`path()`、`readStream()` 等。
- 支持具名工作区和匿名闭包，通过 Laravel 容器注入依赖。
- `handle()` 或闭包的返回值就是执行结果。
- 通过生命周期钩子，在删除文件前释放额外资源。

## 目录

- [环境要求](#环境要求)
- [安装](#安装)
- [基本使用](#基本使用)
- [使用方式](#使用方式)
  - [具名工作区](#具名工作区)
  - [匿名工作区](#匿名工作区)
  - [仅使用临时目录](#仅使用临时目录)
- [Storage 与配置](#storage-与配置)
- [生命周期与清理](#生命周期与清理)
  - [具名钩子](#具名钩子)
  - [清理回调](#清理回调)
  - [手动清理](#手动清理)
- [API 速查](#api-速查)
- [开发](#开发)
- [许可证](#许可证)

## 环境要求

| 依赖 | 支持版本 |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12.x 中的 12.45 及以上版本，或 13.x |

## 安装

```bash
composer require edram/laravel-temp-workspace
```

Laravel 会自动发现并注册服务提供者。无需额外配置即可使用：工作区跟随 Laravel 的默认文件系统磁盘，目录为 `temporary-workspaces`。

## 基本使用

通过 `create()` 获取独立的临时目录，直接使用 Laravel Storage 方法操作文件：

```php
use Edram\LaravelTempWorkspace\Facades\LaravelTempWorkspace;

$workspace = LaravelTempWorkspace::create();
$workspace->put('hello.txt', 'Hello, workspace!');

$contents = $workspace->get('hello.txt');

// 如需提前删除整个工作区目录及其内容，可取消下一行注释。
// $workspace->destroy();
```

无需手动删除：请求、命令或队列任务的本次执行结束时，包会自动清理整个工作区目录及其内容。

## 使用方式

| 方式 | 适用场景 |
| --- | --- |
| 具名工作区 | 需要复用的工作，有独立的输入参数或生命周期钩子。 |
| 匿名工作区 | 使用闭包即可完成的一次性操作。 |
| 临时目录 | 自行组织工作过程，仅使用临时存储能力。 |

### 具名工作区

在 `app/TempWorkspaces` 中创建类。像 Laravel 的 Job 或 Command 一样，通过构造函数接收输入参数，在 `handle()` 上声明服务依赖。

例如，下载文件并返回 MD5 校验值：

```php
namespace App\TempWorkspaces;

use Edram\LaravelTempWorkspace\TempWorkspace;
use Illuminate\Http\Client\Factory as Http;

final class DownloadFile extends TempWorkspace
{
    public function __construct(
        public readonly string $url,
    ) {}

    public function handle(Http $http): string
    {
        $filename = 'download.bin';

        $this->put(
            $filename,
            $http->timeout(60)->get($this->url)->throw()->body(),
        );

        return $this->checksum($filename, ['checksum_algo' => 'md5']);
    }
}
```

通过管理器执行：

```php
use App\TempWorkspaces\DownloadFile;
use Edram\LaravelTempWorkspace\WorkspaceManager;

$workspace = new DownloadFile($url);
$id = $workspace->id();

$md5 = app(WorkspaceManager::class)->perform($workspace);
```

`handle()` 的返回值就是执行结果。每个实例都有稳定的 ID，可以在执行前读取；每个实例只能准备一次，再次执行或失败重试时应创建新实例。

### 匿名工作区

闭包可以同时接收当前工作区和容器中的其他服务：

```php
use Edram\LaravelTempWorkspace\Facades\LaravelTempWorkspace;
use Edram\LaravelTempWorkspace\TempWorkspace;
use Illuminate\Http\Client\Factory as Http;

$md5 = LaravelTempWorkspace::perform(
    function (TempWorkspace $workspace, Http $http) use ($url): string {
        $workspace->put(
            'download.bin',
            $http->timeout(60)->get($url)->throw()->body(),
        );

        return $workspace->checksum('download.bin', ['checksum_algo' => 'md5']);
    },
);
```

### 仅使用临时目录

通过 `create()` 创建工作区，直接进行文件操作：

```php
use Edram\LaravelTempWorkspace\WorkspaceManager;

$workspace = app(WorkspaceManager::class)->create();
$workspace->put('input.txt', 'Workspace contents');

$contents = $workspace->get('input.txt');
$path = $workspace->path('input.txt');
```

这种方式创建的工作区同样会自动清理。

## Storage 与配置

按需发布 `config/laravel-temp-workspace.php`：

```bash
php artisan vendor:publish --tag=laravel-temp-workspace-config
```

`disk` 默认为 `null`，由 Laravel Storage 解析 `filesystems.default`（对应 Laravel 的 `FILESYSTEM_DISK` 配置）。只有需要工作区使用不同磁盘时，才指定独立配置。

| 配置项 | 环境变量 | 默认值 |
| --- | --- | --- |
| `disk` | `TEMP_WORKSPACE_DISK` | `null`，跟随 `filesystems.default` |
| `directory` | `TEMP_WORKSPACE_DIRECTORY` | `temporary-workspaces` |

按需通过环境变量覆盖：

```dotenv
# TEMP_WORKSPACE_DISK=s3
TEMP_WORKSPACE_DIRECTORY=temporary-workspaces
```

不设置 `TEMP_WORKSPACE_DISK` 时，工作区跟随 Laravel 的默认磁盘。`directory` 是磁盘内的相对目录，不是本地文件系统的绝对路径。

每个工作区位于所选磁盘的 `temporary-workspaces/{id}` 下。工作区使用 Laravel 原生的 `scoped` 驱动，保留父磁盘的目录前缀和配置选项；测试时支持 `Storage::fake()`。

`TempWorkspace` 会把文件操作转发给自己的隔离磁盘。通过 `$workspace->disk()` 可以取得底层的 Laravel `FilesystemAdapter`。

本地磁盘的 `path()` 返回本地文件系统路径；远程磁盘返回存储路径。处理远程文件时，可以通过 Storage 的 `get()`、`readStream()` 或 `checksum()` 读取或计算校验值。URL 相关方法取决于所选驱动提供的能力。

**需要保留的产出，应在清理前持久化。** 如果返回值指向临时文件，应在工作区生命周期结束前，将文件复制或通过流写入永久存储。

## 生命周期与清理

| 使用场景 | 清理时机 |
| --- | --- |
| HTTP 请求 | 响应发送完成后，在应用终止阶段清理。 |
| Artisan 命令 | 命令结束时清理，包括普通异常退出。 |
| 队列任务 | 每次执行结束后清理，包含任务的失败处理阶段。 |

嵌套命令或队列任务只清理自己作用域中的工作区，保留外层生命周期创建的工作区。`perform()` 返回时不会立即触发清理。

自动清理依赖 Laravel 执行相应的生命周期事件。进程被强制终止、队列 worker 超时等情况可能留下临时文件。

### 具名钩子

在具名工作区中按需添加以下方法：

```php
public function failed(\Throwable $exception): void
{
    report($exception);
}

public function terminate(\Psr\Log\LoggerInterface $logger): void
{
    $logger->info('Workspace terminated.', ['workspace_id' => $this->id()]);
}
```

`failed()` 接收 `handle()` 抛出的异常。`terminate()` 在清理阶段、目录删除之前运行，此时仍可访问工作区文件，也可以释放额外资源；该方法的服务依赖由容器注入。

### 清理回调

可以在工作过程中，或针对已经创建的工作区注册额外的清理行为：

```php
use Edram\LaravelTempWorkspace\TempWorkspace;
use Psr\Log\LoggerInterface;

$workspace->terminating(function (TempWorkspace $current, LoggerInterface $logger): void {
    $logger->info('Cleaning temporary files.', ['workspace_id' => $current->id()]);
});
```

回调由容器调用，在 `terminate()` 之后、目录删除之前执行。某个钩子失败不会阻止后续钩子和清理操作。钩子及目录清理异常会通过 Laravel 的异常处理器报告；如果 `failed()` 自身抛错，仍会向调用方抛出原始工作异常。

### 手动清理

调用 `$workspace->destroy()` 可立即删除当前工作区的目录及其内容，返回布尔值。它只负责删除文件；生命周期钩子仍由管理器在清理时执行，因此钩子不应依赖已删除的文件。

对于长时间运行的工作，可以在使用或持久化结果后，显式清理管理器当前追踪的全部工作区：

```php
use Edram\LaravelTempWorkspace\WorkspaceManager;

app(WorkspaceManager::class)->cleanup();
```

该操作影响此管理器追踪的所有作用域。在没有创建新工作区的情况下重复调用，不会再次执行已经完成的清理钩子。

## API 速查

| API | 作用 |
| --- | --- |
| `$manager->create()` | 创建并准备一个临时工作区。 |
| `$manager->perform($work)` | 同步执行工作区实例或闭包，并返回执行结果。 |
| `$manager->cleanup()` | 清理管理器当前追踪的全部工作区。 |
| `$workspace->id()` | 获取当前实例的稳定 ID。 |
| `$workspace->disk()` | 获取已经准备好的 Laravel 文件系统适配器。 |
| `$workspace->destroy()` | 立即删除当前工作区的目录及其内容。 |
| `$workspace->terminating($callback)` | 注册在目录删除前执行的回调。 |
| `$workspace->put()`、`get()`、`path()` 等 | 调用对应的 Laravel 文件系统方法。 |

通过容器解析 `WorkspaceManager`，或使用 `LaravelTempWorkspace` Facade 调用 `create()`、`perform()` 和 `cleanup()`。

## 开发

```bash
composer install
composer test
composer analyse
composer format
composer validate --strict
```

测试使用 Pest 和 Orchestra Testbench，覆盖 HTTP、Artisan、同步队列及数据库队列的生命周期。

## 许可证

MIT，详见 [LICENSE.md](LICENSE.md)。
