<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final readonly class OperationDefinition implements DefinitionInterface
{
	/**
	 * @param callable                $handler
	 * @param list<BehaviorDescriptor> $behaviors
	 */
	public function __construct(
		public string $id,
		public string $name,
		public mixed $handler,
		public array $behaviors = [],
	) {}
}
