<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ServiceCapability;
use Rokke\Runtime\Module\ModuleBuilder;

interface ServiceCapabilityTestRepository {}
final class ServiceCapabilityTestRepositoryImpl implements ServiceCapabilityTestRepository {}

final class ServiceCapabilityTest extends TestCase
{
	public function testContractAndImplementationAreSameWhenOnlyOneArgumentGiven(): void
	{
		$cap = new ServiceCapability(ServiceCapabilityTestRepositoryImpl::class);

		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->contract);
		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->implementation);
	}

	public function testContractAndImplementationAreDistinctWhenBothGiven(): void
	{
		$cap = new ServiceCapability(
			ServiceCapabilityTestRepository::class,
			ServiceCapabilityTestRepositoryImpl::class,
		);

		$this->assertSame(ServiceCapabilityTestRepository::class, $cap->contract);
		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->implementation);
	}

	public function testModuleBuilderServiceWithSingleArgAddsCapabilityWithContractAsImplementation(): void
	{
		$builder = new ModuleBuilder();
		$builder->service(ServiceCapabilityTestRepositoryImpl::class);

		$capabilities = $builder->getCapabilities();

		$this->assertCount(1, $capabilities);
		$this->assertInstanceOf(ServiceCapability::class, $capabilities[0]);

		/** @var ServiceCapability $cap */
		$cap = $capabilities[0];
		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->contract);
		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->implementation);
	}

	public function testModuleBuilderServiceWithTwoArgsAddsCapabilityWithDistinctContractAndImplementation(): void
	{
		$builder = new ModuleBuilder();
		$builder->service(
			ServiceCapabilityTestRepository::class,
			ServiceCapabilityTestRepositoryImpl::class,
		);

		$capabilities = $builder->getCapabilities();

		$this->assertCount(1, $capabilities);

		/** @var ServiceCapability $cap */
		$cap = $capabilities[0];
		$this->assertSame(ServiceCapabilityTestRepository::class, $cap->contract);
		$this->assertSame(ServiceCapabilityTestRepositoryImpl::class, $cap->implementation);
	}

	public function testMultipleServiceCallsAccumulateCapabilities(): void
	{
		$builder = new ModuleBuilder();
		$builder->service(ServiceCapabilityTestRepositoryImpl::class);
		$builder->service(
			ServiceCapabilityTestRepository::class,
			ServiceCapabilityTestRepositoryImpl::class,
		);

		$this->assertCount(2, $builder->getCapabilities());
	}
}
