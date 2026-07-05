<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

interface MiddlewareInterface
{
	public function handle(OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed;
}
