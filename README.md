# Rokke Runtime

Persistent, coroutine-driven execution platform for modern PHP applications. Foundation of the Rokke Framework.

## Features

- **Coroutine-Driven**: Built on Swoole for non-blocking, concurrent execution
- **Module System**: Pluggable, composable application modules
- **Event Bus**: Publish-subscribe event handling across application
- **Pipeline Architecture**: Handler chains for processing requests
- **Resource Management**: Lifecycle-aware resource pooling and cleanup
- **Service Container**: PSR-11 compatible dependency injection
- **Context Management**: Request-scoped context propagation
- **Diagnostics**: Built-in monitoring, timing, and observability
- **Signal Handling**: OS signal handling and graceful shutdown
- **Worker Management**: Multi-worker orchestration with supervisor

## Requirements

- PHP 8.4+
- Swoole Extension
- Composer

## Installation

```bash
composer require rokke/runtime
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Rokke\Runtime\Builder\ApplicationBuilder;

// Auto-assembles Container, Contexts, Pools, EventBus, and Server
$app = ApplicationBuilder::create('0.0.0.0', 8000);

// Register modules (optional)
// $app->container()->get(ModuleSystemInterface::class)->register(new MyHttpModule());

$app->run();
```

## Architecture

### Core Components

- **Application**: Entry point, orchestrates runtime lifecycle
- **Host**: Environment and system-level operations
- **Context**: Request-scoped data container
- **CoroutineManager**: Coroutine creation and lifecycle
- **ModuleSystem**: Module registration and initialization
- **EventBus**: Async event dispatch
- **PipelineEngine**: Request handler chains
- **ResourceManager**: Pool management and cleanup
- **ServiceContainer**: Dependency resolution
- **Scheduler**: Task scheduling and execution
- **RuntimeSupervisor**: Process monitoring and worker management
- **ErrorManager**: Centralized error handling
- **Diagnostics**: Performance metrics and monitoring

### Module System

Modules extend runtime capabilities. Each module:
- Registers services in container
- Subscribes to lifecycle events
- May define event handlers
- Can manage resources

### Request Flow

1. HTTP request arrives
2. Context created with request data
3. Pipeline executes handler chain
4. Handlers process via coroutines
5. Response returned, context cleaned up

## Development

### Setup

```bash
composer install
composer lint:fix
```

### Testing

```bash
# Run test suite
composer test

# With coverage report
composer test:coverage

# Static analysis
composer analyse

# Full quality check
composer check
```

### Code Standards

- PSR-12 coding standard (auto-enforced via PHP CS Fixer)
- Static analysis via PHPStan (Level 8)
- 100% type hints required
- Test coverage for new code

### Workflow

1. Branch from `main`
2. Make focused changes (one feature/fix per PR)
3. Add tests (unit + integration)
4. Run full check suite: `composer check`
5. Submit PR with clear description

## Testing Strategy

### Unit Tests
- Test individual components in isolation
- Mock dependencies
- Focus on behavior, not implementation

### Integration Tests
- Test component interactions
- Use real instances where practical
- Verify lifecycle and state transitions

### Test Patterns

- Use test fixtures for common setups
- Name tests descriptively: `testDoesXWhenConditionY()`
- One assertion per test when possible
- Group related tests in TestCase classes

## Performance Considerations

- Leverage coroutines for I/O-bound operations
- Use resource pooling for connections
- Monitor diagnostics for bottlenecks
- Profile with Swoole profiler before optimizing

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed contribution guidelines.

## License

MIT License - see [LICENSE](LICENSE) file.

## Support

- **Issues**: [GitHub Issues](https://github.com/rokke-php/runtime/issues)
- **Documentation**: [rokke.dev](https://rokke.dev)
- **Source**: [GitHub Repository](https://github.com/rokke-php/runtime)
