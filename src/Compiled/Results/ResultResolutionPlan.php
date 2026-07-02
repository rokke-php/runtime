<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

final readonly class ResultResolutionPlan
{
	public function __construct(public ResultInstructionInterface $instruction) {}

	public function resolve(mixed $value): mixed
	{
		return $this->instruction->resolve($value);
	}
}
