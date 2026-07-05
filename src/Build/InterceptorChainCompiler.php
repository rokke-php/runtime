<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Compiled\CompiledInterceptorChain;
use Rokke\Runtime\Contracts\InvokerInterceptorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

final class InterceptorChainCompiler
{
	/**
	 * Instantiates each interceptor once and closes over the instance.
	 * The returned chain carries pre-built callables — no reflection on the hot path.
	 *
	 * @param InvokerInterceptorDescriptor[] $descriptors
	 */
	public function compile(array $descriptors): CompiledInterceptorChain
	{
		if ($descriptors === []) {
			return CompiledInterceptorChain::empty();
		}

		usort($descriptors, static fn (InvokerInterceptorDescriptor $a, InvokerInterceptorDescriptor $b): int => $a->priority <=> $b->priority);

		$stages = array_map(function (InvokerInterceptorDescriptor $d): callable {
			/** @var InvokerInterceptorInterface $instance */
			$instance = $d->args !== [] ? new ($d->class)(...$d->args) : new ($d->class)();

			/** @return callable(OperationInterface, OperationContextInterface, list<mixed>, callable(list<mixed>): mixed): mixed */
			return static function (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next) use ($instance): mixed {
				/** @var list<mixed> $args */
				return $instance->intercept($op, $ctx, $args, $next);
			};
		}, $descriptors);

		return new CompiledInterceptorChain(array_values($stages));
	}
}
