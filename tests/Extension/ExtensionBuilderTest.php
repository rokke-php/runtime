<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;
use Rokke\Runtime\Extension\ExtensionBuilder;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class ProviderDouble implements DiscoveryProviderInterface
{
	/** @return list<CapabilityInterface> */
	public function discover(): array
	{
		return [];
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ExtensionBuilderTest extends TestCase
{
	private ExtensionBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new ExtensionBuilder();
	}

	public function testGetDiscoveryProvidersEmptyByDefault(): void
	{
		$this->assertSame([], $this->builder->getDiscoveryProviders());
	}

	public function testAddDiscoveryProviderStoresProvider(): void
	{
		$provider = new ProviderDouble();

		$this->builder->addDiscoveryProvider($provider);

		$this->assertSame([$provider], $this->builder->getDiscoveryProviders());
	}

	public function testMultipleProvidersPreserveRegistrationOrder(): void
	{
		$a = new ProviderDouble();
		$b = new ProviderDouble();
		$c = new ProviderDouble();

		$this->builder->addDiscoveryProvider($a);
		$this->builder->addDiscoveryProvider($b);
		$this->builder->addDiscoveryProvider($c);

		$this->assertSame([$a, $b, $c], $this->builder->getDiscoveryProviders());
	}

	public function testCapabilitiesAndProvidersAreIndependent(): void
	{
		$provider = new ProviderDouble();

		$this->builder->addDiscoveryProvider($provider);

		$this->assertSame([], $this->builder->getCapabilities());
		$this->assertSame([$provider], $this->builder->getDiscoveryProviders());
	}
}
