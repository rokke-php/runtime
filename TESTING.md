# Testing Guide

Comprehensive testing documentation for Rokke Runtime.

## Quick Start

```bash
# Run all tests
composer test

# With coverage
composer test:coverage

# Specific test
vendor/bin/phpunit tests/Unit/ContextTest.php

# Specific method
vendor/bin/phpunit tests/Unit/ContextTest.php::testContextStoresData
```

## Test Organization

```
tests/
├── Unit/                      # Component isolation
│   ├── Runtime/
│   │   ├── ApplicationTest.php
│   │   ├── ContextTest.php
│   │   ├── ContextManagerTest.php
│   │   ├── CoroutineManagerTest.php
│   │   ├── ServiceContainerTest.php
│   │   ├── LifecycleTest.php
│   │   ├── ResourceManagerTest.php
│   │   └── ...
│   ├── Module/
│   ├── Event/
│   ├── Pipeline/
│   └── ...
├── Integration/                # Multi-component
│   ├── RuntimeBootstrapTest.php
│   ├── RequestLifecycleTest.php
│   ├── ModuleIntegrationTest.php
│   └── ...
├── Fixtures/                  # Shared test data
│   ├── MockModule.php
│   ├── TestHandler.php
│   └── ...
└── ApplicationStateTest.php    # Cross-cutting
```

## Unit Tests

Test individual components in isolation.

### Characteristics
- Mock all dependencies
- Single responsibility per test
- Fast execution
- No I/O or network
- Deterministic

### Example Pattern

```php
<?php

namespace Rokke\Runtime\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Runtime\Context;

class ContextTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context([], []);
    }

    public function testContextStoresData(): void
    {
        $this->context->set('key', 'value');
        $this->assertEquals('value', $this->context->get('key'));
    }

    public function testContextThrowsOnMissingKey(): void
    {
        $this->expectException(ContextKeyNotFoundException::class);
        $this->context->get('nonexistent');
    }
}
```

## Integration Tests

Test component interactions.

### Characteristics
- Use real instances (not mocks)
- Test contract between components
- Include lifecycle transitions
- Focus on integration points
- May be slower than unit tests

### Example Pattern

```php
<?php

namespace Rokke\Runtime\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Builder\ApplicationBuilder;
use Rokke\Runtime\Contracts\ModuleSystemInterface;

class RuntimeBootstrapTest extends TestCase
{
    public function testApplicationBootstrapsSuccessfully(): void
    {
        $app = ApplicationBuilder::create('127.0.0.1', 8000);
        
        $this->assertNotNull($app->container());
        $this->assertNotNull($app->container()->get(ModuleSystemInterface::class));
    }

    public function testModulesCanBeRegistered(): void
    {
        $app = ApplicationBuilder::create('127.0.0.1', 8000);
        $moduleSystem = $app->container()->get(ModuleSystemInterface::class);
        
        $moduleSystem->register(new TestModule());
        
        $this->assertTrue($moduleSystem->isRegistered(TestModule::class));
    }
}
```

## Fixtures

Reusable test components in `tests/Fixtures/`.

### Common Fixtures

```php
// MockModule - Minimal test module
class MockModule implements ModuleInterface {
    public function name(): string { return 'mock'; }
    public function boot(ServiceContainer $container): void {}
    public function start(): void {}
    public function shutdown(): void {}
}

// TestHandler - Simple request handler
class TestHandler implements HandlerInterface {
    public function handle(Context $context): void {
        $context->response()->write('test');
    }
}
```

Use fixtures to reduce duplication:

```php
class MyTest extends TestCase {
    private MockModule $module;

    protected function setUp(): void {
        $this->module = new MockModule();
    }
}
```

## Mocking

### When to Mock
- External dependencies (DB, HTTP, cache)
- Third-party libraries
- Filesystem operations
- Environment-dependent behavior

### When NOT to Mock
- Rokke Runtime components (use real)
- Application logic
- Integration points between components

### Mocking Example

```php
$mockEventBus = $this->createMock(EventBusInterface::class);
$mockEventBus
    ->expects($this->once())
    ->method('dispatch')
    ->with('test.event', $this->isInstanceOf(Context::class));

// Use in component under test
$component = new MyComponent($mockEventBus);
$component->doSomething();
```

## Test Methods

### Naming Convention

`testDoesXWhenConditionY()`

```php
public function testThrowsExceptionWhenKeyNotFound(): void { }
public function testReturnsNullWhenNotSet(): void { }
public function testExecutesHandlerInCorrectOrder(): void { }
```

### Test Structure

1. **Arrange**: Setup test data
2. **Act**: Call method under test
3. **Assert**: Verify results

```php
public function testContextStoresAndRetrievesData(): void
{
    // Arrange
    $context = new Context([], []);
    $key = 'test-key';
    $value = ['data' => 'value'];
    
    // Act
    $context->set($key, $value);
    $result = $context->get($key);
    
    // Assert
    $this->assertEquals($value, $result);
}
```

## Assertions

Common PHPUnit assertions:

```php
$this->assertTrue($bool);           // Assert true
$this->assertFalse($bool);          // Assert false
$this->assertEquals($expected, $actual);    // Equality
$this->assertSame($expected, $actual);      // Identity (===)
$this->assertNull($value);          // Is null
$this->assertNotNull($value);       // Not null
$this->assertEmpty($value);         // Empty
$this->assertNotEmpty($value);      // Not empty
$this->assertCount($expected, $array);      // Array size
$this->assertContains($needle, $haystack);  // In array
$this->assertStringContains($needle, $haystack);  // String contains
$this->assertInstanceOf(Class::class, $object);   // Instance check
```

### Exception Testing

```php
public function testThrowsException(): void
{
    $this->expectException(MyException::class);
    $this->expectExceptionMessage('Expected message');
    $this->expectExceptionCode(123);
    
    // Code that should throw
    throw new MyException('Expected message', 123);
}
```

## Data Providers

Test multiple scenarios:

```php
/**
 * @dataProvider invalidInputProvider
 */
public function testRejectsInvalidInput($input): void
{
    $this->expectException(InvalidArgumentException::class);
    new MyClass($input);
}

public static function invalidInputProvider(): array
{
    return [
        'negative' => [-1],
        'zero' => [0],
        'empty string' => [''],
        'null' => [null],
    ];
}
```

## Code Coverage

Generate coverage report:

```bash
composer test:coverage
```

Opens `coverage/index.html` for visual review.

### Coverage Goals

- **New features**: 80%+ minimum
- **Critical paths**: 100%
- **Utils/helpers**: 70%+
- **Integration code**: 60%+

Coverage ≠ Quality. Aim for meaningful tests.

## Running Specific Tests

```bash
# All tests in file
vendor/bin/phpunit tests/Unit/ContextTest.php

# Specific test method
vendor/bin/phpunit tests/Unit/ContextTest.php::testContextStoresData

# Tests matching pattern
vendor/bin/phpunit --filter testThrows

# Just unit tests
vendor/bin/phpunit tests/Unit/

# Just integration tests
vendor/bin/phpunit tests/Integration/
```

## Continuous Testing

During development, watch for changes:

```bash
# Manual: re-run on save
vendor/bin/phpunit tests/Unit/ContextTest.php
```

Many IDEs auto-run tests on file change. Enable in your editor.

## Debugging Tests

Add temporary output:

```php
public function testSomething(): void
{
    $result = $this->doSomething();
    var_dump($result);  // Print for inspection
    $this->assertTrue($result);
}
```

Or use PHPUnit verbose:

```bash
vendor/bin/phpunit --verbose
```

## Test Lifecycle Hooks

```php
protected function setUp(): void { }      // Before each test
protected function tearDown(): void { }   // After each test

public static function setUpBeforeClass(): void { }     // Before all
public static function tearDownAfterClass(): void { }   // After all
```

## Best Practices

1. **One assertion per test** (when possible)
   - Easier to debug failures
   - Clearer test intent

2. **Clear, descriptive names**
   - Tell the story of what's tested

3. **Avoid test interdependencies**
   - Each test should run independently

4. **Use fixtures for setup**
   - Reduces duplication
   - Easier to maintain

5. **Test behavior, not implementation**
   - Don't test private methods directly
   - Don't mock too much

6. **Keep tests DRY**
   - Extract common setup to fixtures
   - Use data providers for variants

7. **Test edge cases**
   - Null, empty, negative values
   - Boundary conditions
   - Error paths

## Common Issues

### Test Isolation Problems
- State leaking between tests
- **Solution**: Reset state in `setUp()`

### Flaky Tests
- Non-deterministic failures
- **Solution**: Remove time-dependent logic, use mocks

### Slow Tests
- Too many real I/O operations
- **Solution**: Mock external dependencies

### Hard to Read Tests
- Complex setup or assertions
- **Solution**: Extract to fixtures or helper methods

## CI Integration

Tests run automatically on:
- Pull requests
- Commits to main
- Scheduled jobs

See GitHub Actions for CI configuration.
