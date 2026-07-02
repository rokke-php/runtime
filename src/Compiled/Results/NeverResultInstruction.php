<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

final readonly class NeverResultInstruction implements ResultInstructionInterface
{
	public function resolve(mixed $value): never
	{
		throw new \LogicException('NeverResultInstruction::resolve() is unreachable — the handler declared never and should have thrown before returning.');
	}
}
