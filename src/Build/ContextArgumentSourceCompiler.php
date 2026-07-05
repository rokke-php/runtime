<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionNamedType;
use ReflectionParameter;
use Rokke\Runtime\Compiled\Arguments\ArgumentInstructionInterface;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class ContextArgumentSourceCompiler implements ArgumentSourceCompilerInterface
{
	public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
	{
		$type = $param->getType();

		if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
			return null;
		}

		if (!is_a($type->getName(), OperationContextInterface::class, true)) {
			return null;
		}

		return new ContextArgumentInstruction();
	}
}
