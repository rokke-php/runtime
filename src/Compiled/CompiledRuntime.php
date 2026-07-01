<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

/**
 * Immutable in-memory model of the fully compiled application.
 * All shared structures (pipelines, handlers, plans) are stored once and referenced by ID.
 */
final readonly class CompiledRuntime
{
	/**
	 * @param array<int, mixed>   $pipelines
	 * @param array<int, callable> $handlers
	 * @param array<int, mixed>   $argumentPlans
	 * @param array<int, mixed>   $resultPlans
	 * @param array<string, CompiledOperation> $operations
	 */
	public function __construct(
		public array $pipelines,
		public array $handlers,
		public array $argumentPlans,
		public array $resultPlans,
		public array $operations,
	) {}

	public function getOperation(string $id): ?CompiledOperation
	{
		return $this->operations[$id] ?? null;
	}
}
