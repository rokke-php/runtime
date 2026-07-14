<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Contracts\Execution\ExecutionInterceptorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;

/**
 * Global observability wrapper applied to every execution regardless of transport.
 *
 * Interceptors run in declared order, outermost-first. They observe the execution
 * without altering functional results. A single shared instance lives in CompiledRuntime.
 */
final readonly class CompiledInterceptorPipeline
{
	/** @param list<ExecutionInterceptorInterface> $interceptors */
	public function __construct(private array $interceptors) {}

	public static function empty(): self
	{
		return new self([]);
	}

	public function execute(OperationContextInterface $context, callable $core): mixed
	{
		$chain = array_reduce(
			array_reverse($this->interceptors),
			static fn (callable $next, ExecutionInterceptorInterface $interceptor): \Closure =>
				static fn (): mixed => $interceptor->intercept($context, $next),
			$core,
		);

		return ($chain)();
	}
}
