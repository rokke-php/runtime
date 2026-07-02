<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final class ServiceModelBuilderPass implements ModelBuilderPassInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		foreach ($capabilities as $capability) {
			if (!$capability instanceof ServiceCapability) {
				continue;
			}

			/** @var list<class-string> $aliases */
			$aliases = $capability->contract === $capability->implementation
				? [$capability->contract]
				: [$capability->contract, $capability->implementation];

			$model->add(new ServiceDescriptor(
				contract: $capability->contract,
				implementation: $capability->implementation,
				aliases: $aliases,
			));
		}
	}
}
