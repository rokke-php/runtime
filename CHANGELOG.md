# Changelog

All notable changes to `rokke/runtime` are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.1.0] — 2026-06-30

### Added

- `Application` — entry point; drives lifecycle transitions and delegates to `Host`
- `ApplicationBuilder` — single static factory wiring all components; two-phase build (modules → `CompiledRuntime` → `ExecutionEngine`)
- `Lifecycle` — linear state machine (`Created → Bootstrapping → Starting → Running → Stopping → Stopped`); collects hook failures and re-raises after all hooks run
- `ServiceContainer` — PSR-11 DI with singletons, transients, aliases, factory closures, and pooled bindings with coroutine-scoped auto-release
- `ContextManager` — per-coroutine request context via Swoole coroutine context storage
- `Context` — request-scoped key/value store with best-effort destroy callbacks
- `CoroutineManager` — `go()`, `await()`, `parallel()`, `sleep()`, `cancel()` wrappers over Swoole coroutines
- `ResourcePool` — bounded Swoole Channel-backed connection pool with min/max/timeout
- `ResourceManager` — pool registry implementing `PoolManagerInterface`
- `EventBus` — sync (`dispatchSync`), coroutine-per-listener (`dispatchCoroutine`), and background/distributed stubs
- `PipelineEngine` — composable middleware handler chains
- `ExecutionEngine` — zero-Reflection middleware pipeline; assembles via `array_reduce` + closure nesting
- `Invoker` — resolves `OperationInterface` to its compiled handler by integer ID
- `CompiledRuntime` / `CompiledOperation` — immutable in-memory application graph; integer IDs avoid string lookups on the hot path
- `OperationContext` — per-request execution context with cooperative cancellation (`cancel()` / `throwIfCancelled()`)
- `ModuleSystem` / `ModuleBuilder` — builder-pattern module registration; `buildAll()` drives the build phase
- `Host` — Swoole TCP server adapter; server created lazily on first `start()` call
- `Lifetime` — request lifetime tracker
- Internal contracts: `ContextManagerInterface`, `LifecycleManagerInterface`, `PoolManagerInterface`, `ResourceManagerInterface`, `ModuleSystemInterface`, `InvokerInterface`, `RuntimeInterface`, `ApplicationInterface`, `HostInterface`, and capability/operation/context interfaces
- PHPStan level max with Swoole and PHPUnit stubs
- PHP CS Fixer with `@PSR12` + `@PHP84Migration`
- PHPUnit test suite covering all implemented classes

[0.1.0]: https://github.com/rokke-php/runtime/releases/tag/v0.1.0
