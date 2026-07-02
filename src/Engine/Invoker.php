<?php

declare(strict_types=1);

namespace Rokke\Runtime\Engine;

use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\InvokerInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

/**
 * Resolves the operation to its compiled handler and invokes it.
 * The only place in the hot path that reads CompiledRuntime.
 */
final readonly class Invoker implements InvokerInterface
{
	public function __construct(private CompiledRuntime $runtime) {}

	public function invoke(OperationInterface $operation, OperationContextInterface $context): mixed
	{
		$compiled = $this->runtime->getOperation($operation->id());

		if ($compiled === null) {
			throw new \RuntimeException("No compiled operation found for id '{$operation->id()}'.");
		}

		$handler = $this->runtime->handlers[$compiled->handlerId] ?? null;

		if ($handler === null) {
			throw new \RuntimeException("Handler #{$compiled->handlerId} not found in CompiledRuntime.");
		}

		$argumentPlan = $this->runtime->argumentPlans[$compiled->argumentPlanId] ?? null;

		if ($argumentPlan === null) {
			throw new \RuntimeException("ArgumentResolutionPlan #{$compiled->argumentPlanId} not found in CompiledRuntime.");
		}

		$resultPlan = $this->runtime->resultPlans[$compiled->resultPlanId] ?? null;

		if ($resultPlan === null) {
			throw new \RuntimeException("ResultResolutionPlan #{$compiled->resultPlanId} not found in CompiledRuntime.");
		}

		return $resultPlan->resolve($handler(...$argumentPlan->resolveAll($context)));
	}
}
