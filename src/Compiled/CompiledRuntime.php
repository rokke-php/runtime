<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;

/**
 * Immutable in-memory model of the fully compiled application.
 * All shared structures (pipelines, handlers, plans) are stored once and referenced by ID.
 */
final class CompiledRuntime
{
	public readonly FactoryRepository $factories;
	public readonly OperationRepository $operations;

	/**
	 * @param array<int, mixed>                  $pipelines
	 * @param array<int, callable>               $handlers
	 * @param array<int, ArgumentResolutionPlan> $argumentPlans
	 * @param array<int, ResultResolutionPlan>   $resultPlans
	 */
	public function __construct(
		public readonly array $pipelines,
		public readonly array $handlers,
		public readonly array $argumentPlans,
		public readonly array $resultPlans,
		?OperationRepository $operations = null,
		?FactoryRepository $factories = null,
	) {
		$this->operations = $operations ?? OperationRepository::empty();
		$this->factories  = $factories ?? FactoryRepository::empty();
	}

	public function getService(string $alias): ?CompiledFactory
	{
		return $this->factories->get($alias);
	}
}
