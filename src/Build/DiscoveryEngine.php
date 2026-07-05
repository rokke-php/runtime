<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;

/**
 * Runs all registered discovery providers and merges their capabilities.
 *
 * Called by ApplicationKernel before the ModelBuilder processes capabilities,
 * so discovered capabilities are indistinguishable from explicitly registered ones.
 */
final class DiscoveryEngine
{
	/**
	 * @param  list<DiscoveryProviderInterface> $providers
	 * @return list<CapabilityInterface>
	 */
	public function run(array $providers): array
	{
		$capabilities = [];

		foreach ($providers as $provider) {
			foreach ($provider->discover() as $capability) {
				$capabilities[] = $capability;
			}
		}

		return $capabilities;
	}
}
