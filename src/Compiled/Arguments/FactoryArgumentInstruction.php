<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class FactoryArgumentInstruction implements ArgumentInstructionInterface
{
	public function __construct(public int $factoryId) {}

	public function resolve(OperationContextInterface $context, FactoryRepository $factories): object
	{
		return $factories->create($this->factoryId);
	}
}
