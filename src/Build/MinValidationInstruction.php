<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Exception\ValidationException;

final readonly class MinValidationInstruction implements ValidationInstructionInterface
{
	public function __construct(public int|float $min) {}

	public function validate(mixed $value, string $paramName): void
	{
		if (!is_int($value) && !is_float($value)) {
			return;
		}

		if ($value < $this->min) {
			throw new ValidationException($paramName, "Parameter '{$paramName}' must be at least {$this->min}, got {$value}.");
		}
	}
}
