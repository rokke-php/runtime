<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final readonly class OperationCapability implements CapabilityInterface
{
	public function __construct(
		public string $id,
		public string $name,
		/** @var callable */
		public mixed $handler,
	) {}
}
