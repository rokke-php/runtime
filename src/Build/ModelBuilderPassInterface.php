<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

interface ModelBuilderPassInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void;
}
