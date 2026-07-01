<?php

declare(strict_types=1);

namespace Rokke\Runtime\Module;

use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\ModuleBuilderInterface;

/**
 * Collects capabilities declared by modules during the build phase.
 * The RuntimeBuilder reads getCapabilities() to compile the application graph.
 */
final class ModuleBuilder implements ModuleBuilderInterface
{
	/** @var list<CapabilityInterface> */
	private array $capabilities = [];

	public function addCapability(CapabilityInterface $capability): void
	{
		$this->capabilities[] = $capability;
	}

	/** @return list<CapabilityInterface> */
	public function getCapabilities(): array
	{
		return $this->capabilities;
	}
}
