<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

final readonly class CompiledFactory
{
    /** @param list<int> $dependencies factory IDs in FactoryRepository */
    public function __construct(
        public string $implementation,
        public array $dependencies = [],
    ) {}
}
