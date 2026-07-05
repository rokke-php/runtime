<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Build\ValidationInstructionInterface;
use Rokke\Runtime\Exception\ValidationException;

final readonly class ParameterValidationPlan
{
	/** @param list<ValidationInstructionInterface> $instructions */
	public function __construct(
		public int $index,
		public string $name,
		public array $instructions,
	) {}

	/** @throws ValidationException */
	public function validate(mixed $value): void
	{
		foreach ($this->instructions as $instruction) {
			$instruction->validate($value, $this->name);
		}
	}
}
