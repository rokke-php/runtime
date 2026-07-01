<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Executes the final handler of an operation.
 * The interception point for telemetry, profiling, and APM instrumentation.
 */
interface InvokerInterface
{
	public function invoke(OperationInterface $operation, OperationContextInterface $context): mixed;
}
