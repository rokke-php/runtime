<?php

declare(strict_types=1);

namespace Rokke\Runtime\Module;

use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;
use Rokke\Contracts\Module\ModuleBuilderInterface;
use Rokke\Runtime\Build\ServiceCapability;

/**
 * Collects capabilities and discovery providers declared by modules during the build phase.
 * The RuntimeBuilder reads getCapabilities() to compile the application graph;
 * the DiscoveryEngine reads getDiscoveryProviders() to discover additional capabilities.
 */
final class ModuleBuilder implements ModuleBuilderInterface
{
	/** @var list<CapabilityInterface> */
	private array $capabilities = [];

	/** @var list<DiscoveryProviderInterface> */
	private array $providers = [];

	public function addCapability(CapabilityInterface $capability): void
	{
		$this->capabilities[] = $capability;
	}

	public function service(string $contract, ?string $implementation = null): void
	{
		$this->addCapability(new ServiceCapability($contract, $implementation));
	}

	public function addDiscoveryProvider(DiscoveryProviderInterface $provider): void
	{
		$this->providers[] = $provider;
	}

	/** @return list<CapabilityInterface> */
	public function getCapabilities(): array
	{
		return $this->capabilities;
	}

	/** @return list<DiscoveryProviderInterface> */
	public function getDiscoveryProviders(): array
	{
		return $this->providers;
	}
}
