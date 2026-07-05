<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final class PipelineModelBuilderPass implements ModelBuilderPassInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		foreach ($capabilities as $capability) {
			if (!$capability instanceof MiddlewareCapability) {
				continue;
			}

			$model->add(new MiddlewareDescriptor(
				class: $capability->class,
				priority: $capability->priority,
			));
		}
	}
}
