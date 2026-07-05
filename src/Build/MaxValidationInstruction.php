<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Exception\ValidationException;

final readonly class MaxValidationInstruction implements ValidationInstructionInterface
{
	public function __construct(private int|float $max) {}

	public function validate(mixed $value, string $paramName): void
	{
		if (!is_int($value) && !is_float($value)) {
			return;
		}

		if ($value > $this->max) {
			throw new ValidationException($paramName, "Parameter '{$paramName}' must be at most {$this->max}, got {$value}.");
		}
	}
}
