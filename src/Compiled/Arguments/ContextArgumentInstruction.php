<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class ContextArgumentInstruction implements ArgumentInstructionInterface
{
	public function resolve(OperationContextInterface $context, FactoryRepository $factories): OperationContextInterface
	{
		return $context;
	}
}
