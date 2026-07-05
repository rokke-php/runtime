<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

interface InvokerInterceptorInterface
{
	/**
	 * @param list<mixed> $args Resolved handler arguments — may be forwarded modified via $next($args)
	 * @param callable(list<mixed>): mixed $next
	 */
	public function intercept(
		OperationInterface $op,
		OperationContextInterface $ctx,
		array $args,
		callable $next,
	): mixed;
}
