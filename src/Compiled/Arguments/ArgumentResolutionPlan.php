<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class ArgumentResolutionPlan
{
	/** @param list<ArgumentInstructionInterface> $instructions */
	public function __construct(public array $instructions) {}

	/** @return list<mixed> */
	public function resolveAll(OperationContextInterface $context, FactoryRepository $factories): array
	{
		return array_values(array_map(
			static fn (ArgumentInstructionInterface $i): mixed => $i->resolve($context, $factories),
			$this->instructions,
		));
	}
}
