<?php

declare(strict_types=1);

namespace Rokke\Runtime\Engine;

use Rokke\Runtime\Compiled\CompiledBehaviorPipeline;
use Rokke\Runtime\Compiled\CompiledPipeline;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\InvokerInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

/**
 * Assembles and dispatches the execution chain for an operation:
 *
 *   CompiledBehaviorPipeline (compiled cross-cutting behaviors, e.g. auth, tx)
 *     └─ CompiledPipeline (transport middleware stages)
 *          └─ Invoker (resolves args, calls handler, maps result)
 *
 * All structure is resolved from CompiledRuntime by integer ID — no discovery at runtime.
 * When $runtime is null, falls back to $middlewares (legacy / standalone test path).
 */
final readonly class ExecutionEngine implements RuntimeInterface
{
	/** @param callable[] $middlewares  Legacy global middlewares; used only when $runtime is null */
	public function __construct(
		private InvokerInterface $invoker,
		private array $middlewares = [],
		private ?CompiledRuntime $runtime = null,
	) {}

	public function execute(OperationInterface $operation, OperationContextInterface $context): mixed
	{
		$core = fn (): mixed => $this->dispatchThroughMiddleware($operation, $context);

		$behaviorPipeline = $this->resolveBehaviorPipeline($operation);

		if ($behaviorPipeline !== null) {
			return $behaviorPipeline->execute($context, $core);
		}

		return $core();
	}

	private function dispatchThroughMiddleware(
		OperationInterface $operation,
		OperationContextInterface $context,
	): mixed {
		$stages = $this->resolveMiddlewareStages($operation);
		$invoke = fn (): mixed => $this->invoker->invoke($operation, $context);

		if ($stages === []) {
			return $invoke();
		}

		$chain = array_reduce(
			array_reverse($stages),
			fn (callable $next, callable $stage): \Closure =>
				fn (): mixed => $stage($operation, $context, $next),
			$invoke,
		);

		return ($chain)();
	}

	private function resolveBehaviorPipeline(OperationInterface $operation): ?CompiledBehaviorPipeline
	{
		if ($this->runtime === null) {
			return null;
		}

		$compiled = $this->runtime->operations->find($operation->id());

		if ($compiled === null || $compiled->behaviorPipelineId === null) {
			return null;
		}

		$pipeline = $this->runtime->behaviorPipelines[$compiled->behaviorPipelineId] ?? null;

		return $pipeline instanceof CompiledBehaviorPipeline ? $pipeline : null;
	}

	/** @return callable[] */
	private function resolveMiddlewareStages(OperationInterface $operation): array
	{
		if ($this->runtime === null) {
			return $this->middlewares;
		}

		$compiled = $this->runtime->operations->find($operation->id());

		if ($compiled === null) {
			return [];
		}

		$pipeline = $this->runtime->pipelines[$compiled->pipelineId] ?? null;

		return $pipeline instanceof CompiledPipeline ? $pipeline->stages : [];
	}
}
