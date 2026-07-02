<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

final readonly class ScalarResultInstruction implements ResultInstructionInterface
{
	public function __construct(public string $scalarType) {}

	public function resolve(mixed $value): mixed
	{
		return $value;
	}
}
