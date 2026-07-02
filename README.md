# rokke/runtime

[![CI](https://github.com/rokke-php/runtime/actions/workflows/ci.yml/badge.svg)](https://github.com/rokke-php/runtime/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/github/v/tag/rokke-php/runtime?label=version)](https://github.com/rokke-php/runtime/releases)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.4-8892be)](https://www.php.net)
[![License](https://img.shields.io/github/license/rokke-php/runtime)](LICENSE)

Persistent, coroutine-driven execution platform built on Swoole. Foundation of the Rokke Framework.

## What this is

`rokke/runtime` wires together the core infrastructure every Rokke application needs: lifecycle management, a PSR-11 service container, per-request context propagation, resource pooling, an event bus, a middleware pipeline, and a zero-Reflection execution engine. It consumes `rokke/contracts` and exposes a single `ApplicationBuilder` entry point.

## Installation

```bash
composer require rokke/runtime
```

**Requirements:** PHP 8.4+ · Swoole 5.0+

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Rokke\Runtime\Builder\ApplicationBuilder;

$app = ApplicationBuilder::create('0.0.0.0', 8000);

// Register modules before run()
// $app->container()->get(ModuleSystemInterface::class)->register(new MyModule());

$app->run(); // blocks — starts the Swoole TCP server
```

## Core components

| Component | Class | Role |
|---|---|---|
| Application | `Application` | Entry point; drives lifecycle → host |
| ApplicationBuilder | `ApplicationBuilder` | Assembles and wires all components |
| Lifecycle | `Lifecycle` | State machine + hook dispatcher |
| ServiceContainer | `ServiceContainer` | PSR-11 DI with singletons, transients, pooled |
| ContextManager | `ContextManager` | Per-coroutine request context |
| CoroutineManager | `CoroutineManager` | Coroutine creation, await, parallel |
| ResourcePool | `ResourcePool` | Bounded connection pool (Swoole Channel) |
| ResourceManager | `ResourceManager` | Pool registry and lifecycle |
| EventBus | `EventBus` | Sync, coroutine, and background dispatch |
| PipelineEngine | `PipelineEngine` | Middleware handler chains |
| ExecutionEngine | `ExecutionEngine` | Zero-Reflection middleware + invoker pipeline |
| ModuleSystem | `ModuleSystem` | Builder-pattern module registration |
| Host | `Host` | Swoole TCP server adapter |

## Execution model

```
ApplicationBuilder::create()
  └─ ModuleSystem::buildAll($builder)   ← modules register capabilities
  └─ CompiledRuntime                    ← immutable in-memory graph
  └─ Invoker                            ← resolves operations by integer ID
  └─ ExecutionEngine                    ← wraps Invoker in middleware pipeline
  └─ Application                        ← injects runtime + host + lifecycle
        └─ Host::start($runtime)        ← Swoole server starts, blocks
```

Operations are compiled once at build time. Integer IDs avoid string lookups and memory duplication on the hot path.

## Lifecycle states

`Created → Bootstrapping → Starting → Running → Stopping → Stopped`

Hook into transitions via `LifecycleEventsInterface`:

```php
$lifecycle = $app->container()->get(LifecycleEventsInterface::class);
$lifecycle->onRunning(fn () => printf("Server listening on %s:%d\n", $host, $port));
```

If any hook throws, the remaining hooks still fire and a single `RuntimeException` is raised collecting all messages.

## Stability

This is a `0.x` release. Interfaces in `Rokke\Runtime\Contracts\*` are internal and may change between minor versions. Public-facing contracts live in `rokke/contracts`.

## License

MIT
