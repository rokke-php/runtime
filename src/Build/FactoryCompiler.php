<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionClass;
use ReflectionNamedType;

final class FactoryCompiler
{
	/**
	 * Compiles a ServiceDescriptor into a CompiledFactory using Reflection.
	 *
	 * @param callable(class-string): CompiledFactory $resolver  maps a type to its factory
	 * @throws \RuntimeException if any constructor parameter cannot be resolved at build time
	 */
	public function compile(ServiceDescriptor $descriptor, callable $resolver): CompiledFactory
	{
		$class       = $descriptor->implementation;
		$reflection  = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();

		if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
			return new CompiledFactory(static function () use ($class): object {
				/** @var object */
				return new $class();
			});
		}

		$argFactories = [];

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
			$typeName       = $type->getName();
			$argFactories[] = $resolver($typeName);
		}

		return new CompiledFactory(static function () use ($class, $argFactories): object {
			$args = array_map(static fn (CompiledFactory $f): object => $f->create(), $argFactories);

			/** @var object */
			$instance = new $class(...$args);

			return $instance;
		});
	}
}
