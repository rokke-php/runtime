<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Compiled\CompiledPipeline;
use Rokke\Runtime\Contracts\MiddlewareInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

final class PipelineCompiler
{
	/**
	 * Instantiates each middleware once and closes over the instance.
	 * The returned pipeline carries pre-built callables — no reflection on the hot path.
	 *
	 * @param MiddlewareDescriptor[] $descriptors
	 */
	public function compile(array $descriptors): CompiledPipeline
	{
		if ($descriptors === []) {
			return CompiledPipeline::empty();
		}

		usort($descriptors, static fn (MiddlewareDescriptor $a, MiddlewareDescriptor $b): int => $a->priority <=> $b->priority);

		$stages = array_map(function (MiddlewareDescriptor $d): callable {
			/** @var MiddlewareInterface $instance */
			$instance = $d->args !== [] ? new ($d->class)(...$d->args) : new ($d->class)();

			return static fn (OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed =>
				$instance->handle($op, $ctx, $next);
		}, $descriptors);

		return new CompiledPipeline(array_values($stages));
	}
}
