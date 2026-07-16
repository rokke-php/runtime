<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final readonly class OperationCapability implements CapabilityInterface
{
	/**
	 * @param class-string $handler
	 */
	public function __construct(
		public string $id,
		public string $name,
		public string $handler,
	) {}
}
