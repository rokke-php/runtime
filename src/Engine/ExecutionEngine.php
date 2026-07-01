<?php

declare(strict_types=1);

namespace Rokke\Runtime\Engine;

use Rokke\Runtime\Contracts\InvokerInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

/**
 * Assembles the middleware pipeline around the Invoker and dispatches execution.
 * Pipelines are built by nesting closures (array_reverse order) — zero Reflection.
 */
final readonly class ExecutionEngine implements RuntimeInterface
{
	/** @param array<callable> $middlewares */
	public function __construct(
		private InvokerInterface $invoker,
		private array $middlewares = [],
	) {}

	public function execute(OperationInterface $operation, OperationContextInterface $context): mixed
	{
		$core = fn (): mixed => $this->invoker->invoke($operation, $context);

		$pipeline = array_reduce(
			array_reverse($this->middlewares),
			fn (callable $next, callable $middleware): \Closure =>
				fn (): mixed => $middleware($operation, $context, $next),
			$core,
		);

		return ($pipeline)();
	}
}
