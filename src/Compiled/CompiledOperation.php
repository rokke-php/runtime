<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

/**
 * Resolved operation: carries its own identity plus integer indices into
 * CompiledRuntime tables to avoid memory duplication.
 * Immutable value object — created once at Build time, reused on every request.
 */
final readonly class CompiledOperation
{
	public function __construct(
		public string $id,
		public int    $pipelineId,
		public int    $factoryId,
		public int    $argumentPlanId,
		public int    $resultPlanId,
		public int    $interceptorChainId = 0,
		public int    $validationPlanId = 0,
		public ?int   $behaviorPipelineId = null,
	) {}
}
