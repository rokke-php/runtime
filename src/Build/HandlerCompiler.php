<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionClass;

final class HandlerCompiler
{
	/**
	 * Resolves a handler class-string into a callable ready for the execution pipeline.
	 *
	 * Validates that the class declares a public __invoke() method, then either
	 * reuses an already-compiled factory from the repository or auto-compiles one
	 * by reflecting on the handler's constructor dependencies.
	 *
	 * @param class-string $handlerClass
	 */
	public function compile(string $handlerClass, FactoryRepository $factories): callable
	{
		$reflection = new ReflectionClass($handlerClass);

		if (!$reflection->hasMethod('__invoke') || !$reflection->getMethod('__invoke')->isPublic()) {
			throw new \RuntimeException(
				"Handler {$handlerClass} must declare a public __invoke() method.",
			);
		}

		$factory = $factories->get($handlerClass);

		if ($factory === null) {
			$descriptor = new ServiceDescriptor($handlerClass, $handlerClass, [$handlerClass]);
			$factory    = (new FactoryCompiler())->compile(
				$descriptor,
				static function (string $dep) use ($factories, $handlerClass): CompiledFactory {
					$f = $factories->get($dep);

					if ($f === null) {
						throw new \RuntimeException(
							"Handler {$handlerClass} depends on '{$dep}' which is not registered as a service.",
						);
					}

					return $f;
				},
			);
		}

		return static fn (mixed ...$args): mixed => ($factory->create())(...$args);
	}
}
