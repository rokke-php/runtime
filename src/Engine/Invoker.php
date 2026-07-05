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
		$compiled = $this->runtime->operations->find($operation->id());

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

		$resolvedArgs   = $argumentPlan->resolveAll($context);
		$validationPlan = $this->runtime->validationPlans[$compiled->validationPlanId] ?? null;

		if ($validationPlan !== null && !$validationPlan->isEmpty()) {
			$validationPlan->validate($resolvedArgs);
		}

		$core = fn (array $args): mixed => $resultPlan->resolve($handler(...$args));

		$chain = $this->runtime->interceptorChains[$compiled->interceptorChainId] ?? null;

		if ($chain === null || $chain->isEmpty()) {
			return $core($resolvedArgs);
		}

		$runner = array_reduce(
			array_reverse($chain->stages),
			fn (callable $proceed, callable $stage): \Closure =>
				function (array $args) use ($stage, $operation, $context, $proceed): mixed {
					/** @var list<mixed> $args */
					return $stage($operation, $context, $args, fn (array $newArgs) => $proceed($newArgs));
				},
			$core,
		);

		return $runner($resolvedArgs);
	}
}
