<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final class InvokerInterceptorModelBuilderPass implements ModelBuilderPassInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		foreach ($capabilities as $capability) {
			if (!$capability instanceof InvokerInterceptorCapability) {
				continue;
			}

			$model->add(new InvokerInterceptorDescriptor(
				class: $capability->class,
				priority: $capability->priority,
				args: $capability->args,
			));
		}
	}
}
