<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Exception\ValidationException;

interface ValidationInstructionInterface
{
	/** @throws ValidationException */
	public function validate(mixed $value, string $paramName): void;
}
