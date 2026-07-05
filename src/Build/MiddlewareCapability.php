<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final readonly class MiddlewareCapability implements CapabilityInterface
{
	/** @param class-string $class */
	public function __construct(
		public string $class,
		public int $priority = 0,
	) {}
}
