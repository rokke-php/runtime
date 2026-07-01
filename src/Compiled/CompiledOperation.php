<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

/**
 * Resolved operation: integer IDs point into CompiledRuntime tables to avoid memory duplication.
 * Immutable value object — created once at build time, reused on every request.
 */
final readonly class CompiledOperation
{
	public function __construct(
		public int $pipelineId,
		public int $handlerId,
		public int $argumentPlanId,
		public int $resultPlanId,
	) {}
}
