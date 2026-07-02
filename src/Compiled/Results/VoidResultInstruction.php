<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

final readonly class VoidResultInstruction implements ResultInstructionInterface
{
	public function resolve(mixed $value): mixed
	{
		return null;
	}
}
