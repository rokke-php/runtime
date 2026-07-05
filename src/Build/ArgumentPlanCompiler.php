<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionFunction;
use ReflectionNamedType;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;

final class ArgumentPlanCompiler
{
	/** @var list<ArgumentSourceCompilerInterface> */
	private readonly array $sources;

	/** @param list<ArgumentSourceCompilerInterface> $sources Extra sources prepended before the built-in ones. */
	public function __construct(array $sources = [])
	{
		$this->sources = [
			...$sources,
			new ContextArgumentSourceCompiler(),
			new ServiceArgumentSourceCompiler(),
		];
	}

	public function compile(callable $handler, FactoryRepository $factories): ArgumentResolutionPlan
	{
		$reflection   = new ReflectionFunction(\Closure::fromCallable($handler));
		$instructions = [];

		foreach ($reflection->getParameters() as $param) {
			$type = $param->getType();

			if (!$type instanceof ReflectionNamedType) {
				throw new \RuntimeException(
					"Parameter \${$param->getName()} has no usable type hint and cannot be resolved.",
				);
			}

			$instruction = null;

			foreach ($this->sources as $source) {
				$instruction = $source->compile($param, $factories);

				if ($instruction !== null) {
					break;
				}
			}

			if ($instruction === null) {
				throw new \RuntimeException(
					"No argument source can resolve parameter \${$param->getName()} (type: {$type->getName()}).",
				);
			}

			$instructions[] = $instruction;
		}

		return new ArgumentResolutionPlan($instructions);
	}
}
