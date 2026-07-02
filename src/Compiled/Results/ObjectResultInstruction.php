<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

final readonly class ObjectResultInstruction implements ResultInstructionInterface
{
	/** @param class-string $contract */
	public function __construct(public string $contract) {}

	public function resolve(mixed $value): mixed
	{
		return $value;
	}
}
