<?php

declare(strict_types=1);

namespace Rokke\Runtime\Extension;

use Rokke\Contracts\Configuration\ConfigurationDescriptorInterface;
use Rokke\Contracts\Extension\ExtensionBuilderInterface;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;
use Rokke\Runtime\Build\ServiceCapability;

/**
 * Collects capabilities, discovery providers, and configuration descriptors
 * declared by extensions during the build phase.
 */
final class ExtensionBuilder implements ExtensionBuilderInterface
{
	/** @var list<CapabilityInterface> */
	private array $capabilities = [];

	/** @var list<DiscoveryProviderInterface> */
	private array $providers = [];

	/** @var list<ConfigurationDescriptorInterface> */
	private array $configurationDescriptors = [];

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

	public function configuration(ConfigurationDescriptorInterface $descriptor): void
	{
		$this->configurationDescriptors[] = $descriptor;
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

	/** @return list<ConfigurationDescriptorInterface> */
	public function getConfigurationDescriptors(): array
	{
		return $this->configurationDescriptors;
	}
}
