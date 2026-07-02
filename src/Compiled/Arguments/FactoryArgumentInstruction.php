<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class FactoryArgumentInstruction implements ArgumentInstructionInterface
{
	public function __construct(private CompiledFactory $factory) {}

	public function resolve(OperationContextInterface $context): object
	{
		return $this->factory->create();
	}
}
