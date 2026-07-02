<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Rokke\Runtime\Compiled\Results\NeverResultInstruction;
use Rokke\Runtime\Compiled\Results\ObjectResultInstruction;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;

final class ResultPlanCompiler
{
	private const SCALAR_TYPES = ['string', 'int', 'float', 'bool', 'array'];

	public function compile(callable $handler): ResultResolutionPlan
	{
		$reflection = new ReflectionFunction(\Closure::fromCallable($handler));
		$returnType = $reflection->getReturnType();

		if ($returnType === null) {
			throw new \RuntimeException(
				'Handler has no return type. All operations must declare an Output Contract (e.g. ": UserDto", ": string", ": void").',
			);
		}

		if ($returnType instanceof ReflectionUnionType || $returnType instanceof ReflectionIntersectionType) {
			throw new \RuntimeException(
				'Union and intersection return types are not supported as Output Contracts. Declare a single concrete type.',
			);
		}

		if (!$returnType instanceof ReflectionNamedType) {
			throw new \RuntimeException('Unsupported return type shape.');
		}

		$typeName = $returnType->getName();

		if ($typeName === 'mixed') {
			throw new \RuntimeException(
				"Return type 'mixed' is not allowed as an Output Contract. Declare a specific type so the Build can validate and compile the result pipeline.",
			);
		}

		if ($typeName === 'void') {
			return new ResultResolutionPlan(new VoidResultInstruction());
		}

		if ($typeName === 'never') {
			return new ResultResolutionPlan(new NeverResultInstruction());
		}

		if (in_array($typeName, self::SCALAR_TYPES, true)) {
			return new ResultResolutionPlan(new ScalarResultInstruction($typeName));
		}

		if (!$returnType->isBuiltin()) {
			/** @var class-string $typeName */
			return new ResultResolutionPlan(new ObjectResultInstruction($typeName));
		}

		throw new \RuntimeException(
			"Built-in type '{$typeName}' is not a recognized Output Contract.",
		);
	}
}
