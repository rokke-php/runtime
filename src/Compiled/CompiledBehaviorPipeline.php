<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Contracts\ExecutionBehaviorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;

final readonly class CompiledBehaviorPipeline
{
	/** @param list<ExecutionBehaviorInterface> $behaviors */
	public function __construct(private array $behaviors) {}

	public function execute(OperationContextInterface $context, callable $handler): mixed
	{
		$chain = array_reduce(
			array_reverse($this->behaviors),
			static fn (callable $next, ExecutionBehaviorInterface $behavior): \Closure =>
				static fn (): mixed => $behavior->handle($context, $next),
			$handler,
		);

		return ($chain)();
	}
}
