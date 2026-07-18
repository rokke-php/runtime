<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

final readonly class CompiledFactory
{
	/**
	 * @param class-string      $implementation
	 * @param list<int>         $dependencies   factory IDs in FactoryRepository
	 * @param list<class-string> $aliases        additional class-string aliases registered for this factory
	 */
	public function __construct(
		public string $implementation,
		public array $dependencies = [],
		public array $aliases = [],
	) {}
}
