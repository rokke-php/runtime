<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionClass;
use ReflectionNamedType;

final class FactoryCompiler
{
	/**
	 * @param callable(class-string): int $resolver  maps a type to its FactoryRepository ID
	 */
	public function compile(ServiceDescriptor $descriptor, callable $resolver): CompiledFactory
	{
		$class       = $descriptor->implementation;
		$reflection  = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();

		if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
			return new CompiledFactory($class);
		}

		$depIds = [];

		foreach ($constructor->getParameters() as $param) {
			$type = $param->getType();

			if (!$type instanceof ReflectionNamedType) {
				throw new \RuntimeException(
					"Parameter \${$param->getName()} of {$class}::__construct() has no usable type hint. " .
					'All injectable parameters must have a class or interface type.',
				);
			}

			if ($type->isBuiltin()) {
				throw new \RuntimeException(
					"Parameter \${$param->getName()} of {$class}::__construct() has built-in type '{$type->getName()}'. " .
					'Only class and interface types are injectable.',
				);
			}

			/** @var class-string $typeName */
			$typeName = $type->getName();
			$depIds[] = $resolver($typeName);
		}

		return new CompiledFactory($class, $depIds);
	}
}
