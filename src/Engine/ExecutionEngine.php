<?php

declare(strict_types=1);

namespace Rokke\Runtime\Engine;

use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

/**
 * Dispatches an operation through the compiled execution infrastructure.
 *
 * The Engine has no knowledge of handlers, argument plans, behaviors, or result
 * plans. It only coordinates two compiled artefacts:
 *
 *   CompiledInterceptorPipeline (global observability: telemetry, logging, metrics)
 *       └─ CompiledExecutionPipeline (Argument → Behavior → Invocation → Result)
 *
 * Any new cross-cutting concern should be added via ExecutionInterceptorInterface,
 * not by modifying this class.
 */
final readonly class ExecutionEngine implements RuntimeInterface
{
	public function __construct(private CompiledRuntime $runtime) {}

	public function execute(OperationInterface $operation, OperationContextInterface $context): mixed
	{
		$compiled = $this->runtime->operations->find($operation->id())
			?? throw new \RuntimeException("No compiled operation found for id '{$operation->id()}'.");

		$core = fn (): mixed => $this->runtime->executionPipeline->execute($compiled, $context);

		return $this->runtime->interceptorPipeline->execute($context, $core);
	}
}
