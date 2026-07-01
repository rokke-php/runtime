<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\ServiceContainer;
use RuntimeException;

// ── Fixtures ────────────────────────────────────────────────────────────────

final class ServiceContainerPlainService
{
	public string $value = 'default';
}

final class ServiceContainerServiceWithDep
{
	public function __construct(
		public readonly ServiceContainerPlainService $dep
	) {}
}

final class ServiceContainerServiceWithDefault
{
	public function __construct(
		public readonly string $name = 'rokke'
	) {}
}

interface ServiceContainerContractA {}
final class ServiceContainerImplA implements ServiceContainerContractA {}

// ── Tests ────────────────────────────────────────────────────────────────────

final class ServiceContainerTest extends TestCase
{
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
	}

	public function testResolvesConcreteClassWithoutBinding(): void
	{
		$instance = $this->container->make(ServiceContainerPlainService::class);

		$this->assertInstanceOf(ServiceContainerPlainService::class, $instance);
	}

	public function testAutowiresConstructorDependencies(): void
	{
		$instance = $this->container->make(ServiceContainerServiceWithDep::class);
		assert($instance instanceof ServiceContainerServiceWithDep);

		$this->assertInstanceOf(ServiceContainerServiceWithDep::class, $instance);
		$this->assertInstanceOf(ServiceContainerPlainService::class, $instance->dep);
	}

	public function testUsesDefaultParameterValuesDuringAutowire(): void
	{
		$instance = $this->container->make(ServiceContainerServiceWithDefault::class);
		assert($instance instanceof ServiceContainerServiceWithDefault);

		$this->assertSame('rokke', $instance->name);
	}

	public function testSingletonReturnsSameInstance(): void
	{
		$this->container->singleton(ServiceContainerPlainService::class);

		$first  = $this->container->make(ServiceContainerPlainService::class);
		$second = $this->container->make(ServiceContainerPlainService::class);

		$this->assertSame($first, $second);
	}

	public function testTransientReturnsDifferentInstances(): void
	{
		$this->container->transient(ServiceContainerPlainService::class);

		$first  = $this->container->make(ServiceContainerPlainService::class);
		$second = $this->container->make(ServiceContainerPlainService::class);

		$this->assertNotSame($first, $second);
	}

	public function testSingletonWithFactoryClosure(): void
	{
		$this->container->singleton(ServiceContainerPlainService::class, function () {
			$svc = new ServiceContainerPlainService();
			$svc->value = 'from-factory';
			return $svc;
		});

		$instance = $this->container->make(ServiceContainerPlainService::class);
		assert($instance instanceof ServiceContainerPlainService);

		$this->assertSame('from-factory', $instance->value);
	}

	public function testAliasResolvesToAbstract(): void
	{
		$this->container->singleton(ServiceContainerContractA::class, ServiceContainerImplA::class);
		$this->container->alias('contractA', ServiceContainerContractA::class);

		$instance = $this->container->make('contractA');

		$this->assertInstanceOf(ServiceContainerImplA::class, $instance);
	}

	public function testHasReturnsTrueForRegisteredBinding(): void
	{
		$this->container->singleton(ServiceContainerPlainService::class);

		$this->assertTrue($this->container->has(ServiceContainerPlainService::class));
	}

	public function testHasReturnsFalseForUnregisteredId(): void
	{
		$this->assertFalse($this->container->has('SomeMissingService'));
	}

	public function testSelfRegistersInContainer(): void
	{
		$resolved = $this->container->get(ServiceContainer::class);

		$this->assertSame($this->container, $resolved);
	}

	public function testMakePassesParametersToFactory(): void
	{
		$this->container->singleton(ServiceContainerPlainService::class, function (mixed $container, mixed $params) {
			assert(is_array($params));
			$svc = new ServiceContainerPlainService();
			$svc->value = is_string($params['value'] ?? null) ? $params['value'] : 'none';
			return $svc;
		});

		$instance = $this->container->make(ServiceContainerPlainService::class, ['value' => 'injected']);
		assert($instance instanceof ServiceContainerPlainService);

		$this->assertSame('injected', $instance->value);
	}

	public function testThrowsWhenClassNotInstantiable(): void
	{
		// Explicitly bind the interface to itself so the container attempts to
		// instantiate it directly (interfaces are not instantiable).
		$this->container->singleton(ServiceContainerContractA::class, ServiceContainerContractA::class);

		$this->expectException(RuntimeException::class);

		$this->container->make(ServiceContainerContractA::class);
	}

	public function testPooledThrowsWithoutResourceManager(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('ResourceManager not configured');

		$this->container->pooled(ServiceContainerPlainService::class, fn () => new ServiceContainerPlainService(), 1, 5);
	}
}
