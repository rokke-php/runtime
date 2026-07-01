<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final class OperationModelBuilderPass implements ModelBuilderPassInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		foreach ($capabilities as $capability) {
			if (!$capability instanceof OperationCapability) {
				continue;
			}

			$model->add(new OperationDefinition(
				id: $capability->id,
				name: $capability->name,
				handler: $capability->handler,
			));
		}
	}
}
