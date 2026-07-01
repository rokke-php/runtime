<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final readonly class OperationDefinition implements DefinitionInterface
{
	public function __construct(
		public string $id,
		public string $name,
		/** @var callable */
		public mixed $handler,
	) {}
}
