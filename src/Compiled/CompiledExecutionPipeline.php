<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Contracts\OperationContextInterface;

/**
 * Fixed four-stage execution pipeline: Argument → Behavior → Invocation → Result.
 *
 * The stage order is an invariant of the Runtime, not an extension point.
 * One shared instance lives in CompiledRuntime; per-operation variation is
 * expressed entirely through the integer IDs carried by CompiledOperation.
 *
 * Modules extend execution via BehaviorPipeline (functional, per-operation)
 * or CompiledInterceptorPipeline (observability, global). They never modify
 * this pipeline's topology.
 */
final readonly class CompiledExecutionPipeline
{
	/**
	 * @param array<int, ArgumentResolutionPlan>   $argumentPlans
	 * @param array<int, ResultResolutionPlan>     $resultPlans
	 * @param array<int, CompiledBehaviorPipeline> $behaviorPipelines
	 * @param array<int, ValidationPlan>           $validationPlans
	 */
	public function __construct(
		private FactoryRepository $factories,
		private array $argumentPlans,
		private array $resultPlans,
		private array $behaviorPipelines,
		private array $validationPlans,
	) {}

	public function argumentPlan(int $id): ArgumentResolutionPlan
	{
		return $this->argumentPlans[$id]
			?? throw new \RuntimeException("ArgumentResolutionPlan #{$id} not registered.");
	}

	public function resultPlan(int $id): ResultResolutionPlan
	{
		return $this->resultPlans[$id]
			?? throw new \RuntimeException("ResultResolutionPlan #{$id} not registered.");
	}

	public function validationPlan(int $id): ?ValidationPlan
	{
		return $this->validationPlans[$id] ?? null;
	}

	public function execute(CompiledOperation $op, OperationContextInterface $context): mixed
	{
		// ── Stage 1: Argument Resolution ──────────────────────────────────────
		$argPlan = $this->argumentPlans[$op->argumentPlanId]
			?? throw new \RuntimeException("ArgumentResolutionPlan #{$op->argumentPlanId} not found in CompiledExecutionPipeline.");

		$args = $argPlan->resolveAll($context);

		// ── Stage 2: Validation (part of argument stage) ──────────────────────
		$validationPlan = $this->validationPlans[$op->validationPlanId] ?? null;

		if ($validationPlan !== null && !$validationPlan->isEmpty()) {
			$validationPlan->validate($args);
		}

		// ── Stage 3: Invocation (+ optional Behavior wrapping) ────────────────
		$handler = $this->factories->create($op->factoryId);
		assert(is_callable($handler), "Factory ID {$op->factoryId} produced a non-callable object.");

		$resultPlan = $this->resultPlans[$op->resultPlanId]
			?? throw new \RuntimeException("ResultResolutionPlan #{$op->resultPlanId} not found in CompiledExecutionPipeline.");

		$invoke = static fn (): mixed => $resultPlan->resolve($handler(...$args));

		$behaviorPipeline = $op->behaviorPipelineId !== null
			? ($this->behaviorPipelines[$op->behaviorPipelineId] ?? null)
			: null;

		// ── Stage 4: Result Resolution (inside invoke) ────────────────────────
		if ($behaviorPipeline !== null) {
			return $behaviorPipeline->execute($context, $invoke);
		}

		return $invoke();
	}
}
