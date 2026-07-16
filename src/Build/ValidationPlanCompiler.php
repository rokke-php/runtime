<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionMethod;
use Rokke\Runtime\Compiled\ParameterValidationPlan;
use Rokke\Runtime\Compiled\ValidationPlan;

final class ValidationPlanCompiler
{
	/** @param list<ValidationSourceCompilerInterface> $sources */
	public function __construct(private readonly array $sources = []) {}

	/** @param class-string $handlerClass */
	public function compile(string $handlerClass): ValidationPlan
	{
		$reflection = new ReflectionMethod($handlerClass, '__invoke');
		$params     = [];

		foreach ($reflection->getParameters() as $index => $param) {
			$instructions = [];

			foreach ($this->sources as $source) {
				$result = $source->compile($param);

				if ($result !== null) {
					$instructions = [...$instructions, ...$result];
				}
			}

			if ($instructions !== []) {
				$params[] = new ParameterValidationPlan($index, $param->getName(), $instructions);
			}
		}

		return $params !== [] ? new ValidationPlan($params) : ValidationPlan::empty();
	}
}
