<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ServiceCapability;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Build\ServiceModelBuilderPass;

interface PassTestContract {}
final class PassTestImpl implements PassTestContract {}

final class ServiceModelBuilderPassTest extends TestCase
{
	private ServiceModelBuilderPass $pass;
	private ApplicationModel $model;

	protected function setUp(): void
	{
		$this->pass  = new ServiceModelBuilderPass();
		$this->model = new ApplicationModel();
	}

	public function testIgnoresCapabilitiesThatAreNotServiceCapability(): void
	{
		/** @var CapabilityInterface $other */
		$other = $this->createStub(CapabilityInterface::class);

		$this->pass->process([$other], $this->model);

		$this->assertSame([], $this->model->definitions(ServiceDescriptor::class));
	}

	public function testAddsOneDescriptorPerServiceCapability(): void
	{
		$cap = new ServiceCapability(PassTestImpl::class);

		$this->pass->process([$cap], $this->model);

		$this->assertCount(1, $this->model->definitions(ServiceDescriptor::class));
	}

	public function testDescriptorPreservesContract(): void
	{
		$cap = new ServiceCapability(PassTestContract::class, PassTestImpl::class);

		$this->pass->process([$cap], $this->model);

		$descriptor = $this->model->definitions(ServiceDescriptor::class)[0];
		$this->assertSame(PassTestContract::class, $descriptor->contract);
	}

	public function testDescriptorPreservesImplementation(): void
	{
		$cap = new ServiceCapability(PassTestContract::class, PassTestImpl::class);

		$this->pass->process([$cap], $this->model);

		$descriptor = $this->model->definitions(ServiceDescriptor::class)[0];
		$this->assertSame(PassTestImpl::class, $descriptor->implementation);
	}

	public function testAliasesContainOnlyContractWhenContractEqualsImplementation(): void
	{
		$cap = new ServiceCapability(PassTestImpl::class);

		$this->pass->process([$cap], $this->model);

		$descriptor = $this->model->definitions(ServiceDescriptor::class)[0];
		$this->assertSame([PassTestImpl::class], $descriptor->aliases);
	}

	public function testAliasesContainBothContractAndImplementationWhenDistinct(): void
	{
		$cap = new ServiceCapability(PassTestContract::class, PassTestImpl::class);

		$this->pass->process([$cap], $this->model);

		$descriptor = $this->model->definitions(ServiceDescriptor::class)[0];
		$this->assertSame([PassTestContract::class, PassTestImpl::class], $descriptor->aliases);
	}

	public function testMultipleCapabilitiesProduceMultipleDescriptors(): void
	{
		$a = new ServiceCapability(PassTestImpl::class);
		$b = new ServiceCapability(PassTestContract::class, PassTestImpl::class);

		$this->pass->process([$a, $b], $this->model);

		$this->assertCount(2, $this->model->definitions(ServiceDescriptor::class));
	}

	public function testMixedCapabilitiesOnlyProcessServiceCapabilities(): void
	{
		/** @var CapabilityInterface $other */
		$other = $this->createStub(CapabilityInterface::class);
		$cap   = new ServiceCapability(PassTestImpl::class);

		$this->pass->process([$other, $cap, $other], $this->model);

		$this->assertCount(1, $this->model->definitions(ServiceDescriptor::class));
	}
}
