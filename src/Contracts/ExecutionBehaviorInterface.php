<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

interface ExecutionBehaviorInterface
{
	public function handle(OperationContextInterface $context, callable $next): mixed;
}
