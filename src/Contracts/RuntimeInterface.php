<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Central entry point during the execution phase.
 * Accepts a compiled operation and its context, returns the raw result.
 */
interface RuntimeInterface
{
	public function execute(OperationInterface $operation, OperationContextInterface $context): mixed;
}
