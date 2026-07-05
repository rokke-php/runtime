<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Exception\ValidationException;

final readonly class NotBlankValidationInstruction implements ValidationInstructionInterface
{
	public function validate(mixed $value, string $paramName): void
	{
		if (!is_string($value) || trim($value) === '') {
			throw new ValidationException($paramName, "Parameter '{$paramName}' must not be blank.");
		}
	}
}
