<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionFunction;
use ReflectionNamedType;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class ArgumentPlanCompiler
{
	public function compile(callable $handler, FactoryRepository $factories): ArgumentResolutionPlan
	{
		$reflection  = new ReflectionFunction(\Closure::fromCallable($handler));
		$instructions = [];

		foreach ($reflection->getParameters() as $param) {
			$type = $param->getType();

			if (!$type instanceof ReflectionNamedType) {
				throw new \RuntimeException(
					"Parameter \${$param->getName()} has no usable type hint and cannot be resolved.",
				);
			}

			if ($type->isBuiltin()) {
				throw new \RuntimeException(
					"Built-in type '{$type->getName()}' for parameter \${$param->getName()} is not injectable.",
				);
			}

			/** @var class-string $typeName */
			$typeName = $type->getName();

			if (is_a($typeName, OperationContextInterface::class, true)) {
				$instructions[] = new ContextArgumentInstruction();
				continue;
			}

			$factory = $factories->get($typeName);

			if ($factory === null) {
				throw new \RuntimeException(
					"No service registered for type '{$typeName}'. Register it via \$builder->service() before building.",
				);
			}

			$instructions[] = new FactoryArgumentInstruction($factory);
		}

		return new ArgumentResolutionPlan($instructions);
	}
}
