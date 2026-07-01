# Rokke Runtime Manifesto

Rokke Runtime is not a web framework. It's the execution engine beneath frameworks. It exists to make concurrent PHP development safe, fast, and clear.

## Core Purposes

1. **Remove Concurrency Friction**: Build async apps without wrestling with coroutine management, context leaks, or state pollution.
2. **Enforce Clarity**: Type safety and explicit architecture prevent entire classes of bugs.
3. **Enable Composition**: Modules extend Runtime without core changes. Everything is replaceable.
4. **Guarantee Observability**: Performance issues are visible. No mystery failures.

## Design Principles

### Coroutines Are First-Class

Non-blocking execution is foundational. Every component assumes Swoole coroutines.

- I/O doesn't block
- Resources pool and reuse
- Context flows naturally through async boundaries
- Type system prevents coroutine leaks

### No Magic, Ever

Architecture is visible in code. You can trace execution by reading.

- Modules are clear entry points
- Events are named and sequenced
- Dependencies are explicit (service container)
- Lifecycle is managed, not hidden

### Type Safety Is Non-Negotiable

PHP 8.4+. Full type hints. PHPStan Level 8 (maximum strictness).

- Type checks happen at analysis time
- Entire classes of bugs never reach runtime
- IDE support is exceptional
- Refactoring is safe

### Composition Over Inheritance

Extend via modules. Replace via service container. Don't fork.

- Modules are first-class citizens
- Every component can be swapped
- Event bus decouples modules
- No core privilege—modules use same APIs as third-party code

### Lifecycle Is Sacred

Application state is explicit. Startup, running, shutdown—each phase matters.

- Boot phase initializes
- Start phase signals readiness
- Graceful shutdown cleans up
- Worker coordination is supervised

### Observability Built-In

No instrumentation required. Diagnostics are native.

- Timing tracked automatically
- Errors centralized and traceable
- Resource usage measurable
- Metrics available without plugins

## What Runtime Is NOT

**Not a web framework.** HTTP is a module. So are WebSocket, TCP, Queue, Scheduler, gRPC. Framework builders use Runtime.

**Not a replacement for Swoole.** Runtime layers application abstractions over Swoole, making coroutines safer and structured.

**Not monolithic.** Core is focused. Modules extend. Replace what doesn't fit.

**Not slow.** Coroutines are lightweight. Pooling reuses resources. Type analysis catches issues before runtime.

## Architecture Philosophy

```
Application Layer (Frameworks, Apps)
    ↓ uses
Module System
    ↓ uses
Events, Pipeline, Scheduler
    ↓ uses
Service Container, Context Manager
    ↓ uses
Coroutine Manager, Resource Manager
    ↓ uses
Host, Environment, Diagnostics
    ↓ uses
Swoole
```

Each layer has clear responsibility. Layers don't skip. Data flows through the stack.

## Performance Strategy

Performance comes from design, not hacks:

- **Coroutines** → non-blocking I/O by default
- **Pooling** → reuse expensive resources
- **Type Safety** → analysis-time errors, not runtime
- **Measured Approach** → diagnostics guide optimization
- **Minimal Allocation** → low per-request overhead

No special `FastRepository`. No tricks. Speed is built-in.

## The Stability Contract

**We promise:**
- Core APIs stable (Semantic Versioning)
- Security handled responsibly
- Type safety maintained
- Performance doesn't regress
- Clear upgrade paths

**We expect:**
- Issues reported with reproduction steps
- PRs include tests
- Code passes quality gate
- Breaking changes discussed first

## Future Boundaries

Runtime will add:
- Distributed tracing support
- Enhanced observability integrations
- Worker pool patterns
- Caching layers
- Stay focused on core execution

Runtime will NOT become:
- HTTP framework (that's for frameworks)
- ORM or database library
- Templating engine
- Bloated with features that don't scale
- Anything that sacrifices type safety for convenience

## Core Commitment

Rokke Runtime removes friction from concurrent PHP development. It provides the foundation—safe, fast, observable—so you build applications that scale.

Engineered for clarity. Built for performance. Designed for production.

---

**Fernando Duarte**  
Rokke Runtime Maintainer
