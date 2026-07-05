<?php

declare(strict_types=1);

namespace Rokke\Runtime\Engine;

use Rokke\Runtime\Compiled\CompiledPipeline;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\InvokerInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

/**
 * Assembles the middleware pipeline around the Invoker and dispatches execution.
 * Pipelines are built by nesting closures (array_reverse order) — zero Reflection.
 *
 * When $runtime is provided, the pipeline is looked up from the CompiledRuntime
 * by the operation's pipelineId — instances were bound at build time, not per request.
 * When $runtime is null, falls back to the $middlewares array (legacy / test path).
 */
final readonly class ExecutionEngine implements RuntimeInterface
{
	/**
	 * @param callable[]         $middlewares Legacy global middlewares; used only when $runtime is null
	 */
	public function __construct(
		private InvokerInterface $invoker,
		private array $middlewares = [],
		private ?CompiledRuntime $runtime = null,
	) {}

	public function execute(OperationInterface $operation, OperationContextInterface $context): mixed
	{
		$core   = fn (): mixed => $this->invoker->invoke($operation, $context);
		$stages = $this->resolveStages($operation);

		if ($stages === []) {
			return $core();
		}

		$chain = array_reduce(
			array_reverse($stages),
			fn (callable $next, callable $stage): \Closure =>
				fn (): mixed => $stage($operation, $context, $next),
			$core,
		);

		return ($chain)();
	}

	/** @return callable[] */
	private function resolveStages(OperationInterface $operation): array
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
