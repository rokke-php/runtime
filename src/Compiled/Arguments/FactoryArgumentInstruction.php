<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class FactoryArgumentInstruction implements ArgumentInstructionInterface
{
    public function __construct(
        public int $factoryId,
        private FactoryRepository $factories,
    ) {}

    public function resolve(OperationContextInterface $context): object
    {
        return $this->factories->create($this->factoryId);
    }
}
