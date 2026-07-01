<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

interface ModelBuilderInterface
{
	/** @param list<CapabilityInterface> $capabilities */
	public function build(array $capabilities): ApplicationModel;
}
