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
		$compiled = $this->runtime->getOperation($operation->id());

		if ($compiled === null) {
			throw new \RuntimeException("No compiled operation found for id '{$operation->id()}'.");
		}

		$handler = $this->runtime->handlers[$compiled->handlerId] ?? null;

		if ($handler === null) {
			throw new \RuntimeException("Handler #{$compiled->handlerId} not found in CompiledRuntime.");
		}

		return $handler($context);
	}
}
