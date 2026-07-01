# Architecture

Rokke Runtime architecture overview. See README.md for component list.

## Design Principles

1. **Coroutine-First**: Leverage Swoole coroutines for non-blocking I/O
2. **Composable**: Modules extend runtime without core changes
3. **Type-Safe**: Strict typing enforces contracts
4. **Observable**: Built-in diagnostics and monitoring
5. **Resource-Aware**: Explicit lifecycle and cleanup

## Layers

```
┌─────────────────────────────────────┐
│     Application (Entry Point)       │
├─────────────────────────────────────┤
│         Module System               │
│      (Extensibility Layer)          │
├─────────────────────────────────────┤
│   Event Bus │ Pipeline │ Scheduler  │
│         (Request Flow)              │
├─────────────────────────────────────┤
│  Service Container │ Context Mgr    │
│   (Dependency Mgmt)                 │
├─────────────────────────────────────┤
│  Coroutine │ Resource │ Lifecycle   │
│    (Async Execution)                │
├─────────────────────────────────────┤
│  Host │ Environment │ Diagnostics   │
│    (System Integration)             │
├─────────────────────────────────────┤
│          Swoole (HTTP/TCP)          │
└─────────────────────────────────────┘
```

## Core Components

### Application & Host
- **Application**: Bootstraps and runs runtime
- **Host**: Environment abstraction (signals, paths, system info)
- Manages startup, running, shutdown phases

### Dependency Management
- **ServiceContainer**: PSR-11 container
- Manages service registration and resolution
- Singleton and transient scopes
- Lazy loading support

### Request Flow
- **Context**: Request-scoped data container
- **ContextManager**: Context lifecycle
- **Pipeline**: Handler chain pattern
- **EventBus**: Async event dispatch
- Modules can hook into flow via events

### Concurrency
- **CoroutineManager**: Coroutine lifecycle
- Wraps Swoole coroutine API
- Handles parent-child relationships
- Manages context propagation

### Module System
- **ModuleSystem**: Registry and loader
- Modules register services, events, handlers
- Lifecycle callbacks (boot, start, shutdown)
- Enable/disable per-module

### Resource Management
- **ResourceManager**: Pool management
- **ResourcePool**: Typed resource pools
- Automatic cleanup on context end
- Prevents leaks and exhaustion

### Lifecycle
- **Lifecycle**: Phase management
- Events: boot, start, ready, shutdown
- Ordered initialization and cleanup
- Supervisory coordination

### Observability
- **Diagnostics**: Metrics and monitoring
- Timing information
- Error tracking
- Custom metrics support

## Request Lifecycle

```
1. HTTP request arrives
   ↓
2. Host creates Context
   ↓
3. Application dispatches event: request.received
   ↓
4. Modules/Pipeline handle request (coroutines)
   ↓
5. Application dispatches event: request.sending
   ↓
6. Response sent to client
   ↓
7. ContextManager cleans up resources
   ↓
8. Application dispatches event: request.completed
```

## Module Extension Points

Modules can:
1. Register services in container
2. Subscribe to lifecycle events (boot, start, shutdown)
3. Register event handlers for application events
4. Define request pipeline handlers
5. Schedule periodic tasks
6. Manage custom resources

## Error Handling

- **ErrorManager**: Centralized error handling
- Catches unhandled exceptions
- Converts to responses or logs
- Custom error handlers via modules
- Maintains error context for diagnostics

## Signal Handling

- **SignalManager**: OS signal trapping
- Safe signal handling in async context
- Graceful shutdown on SIGTERM/SIGINT
- Custom signal handlers registerable

## Worker Management

- **WorkerManager**: Multi-worker orchestration
- **RuntimeSupervisor**: Process monitoring
- Worker health checks
- Automatic restart on failure
- Coordinated shutdown

## Concurrency Model

### Request Handling
- Each request handled in Swoole coroutine
- Can spawn child coroutines
- Context shared across coroutines
- Automatic cleanup on completion

### Scheduling
- **Scheduler**: Task scheduling
- Runs in separate coroutines
- Can be periodic or one-shot
- Integrated with supervisor

### Event Dispatching
- Events dispatched asynchronously
- Handlers run in coroutines
- Order of handler execution not guaranteed
- Errors don't propagate between handlers

## Performance Considerations

### Hot Path
- Context creation/cleanup
- Handler execution
- Event dispatch
- Pipeline traversal

### Optimizations
- Container compiled (preloaded)
- Handler chain compiled
- Resource pooling
- Minimal allocations per request

### Monitoring
- Use Diagnostics to profile
- Watch context creation time
- Monitor coroutine counts
- Track resource pool utilization

## Type System

All public APIs:
- Fully typed (no weak types)
- Use type hints (parameters + returns)
- Leverage PHP 8.4 features
- Static analysis clean (PHPStan Level 8)

## Testing Strategy

- **Unit**: Components in isolation
- **Integration**: Multi-component flows
- **End-to-End**: Full request lifecycle
- Fixtures for common setups

See CONTRIBUTING.md for testing details.

## Future Considerations

- Distributed tracing support
- Built-in caching layers
- Worker pool patterns
- Performance profiling hooks
