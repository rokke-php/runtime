<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Results;

interface ResultInstructionInterface
{
	public function resolve(mixed $value): mixed;
}
