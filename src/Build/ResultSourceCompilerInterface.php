<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionNamedType;
use Rokke\Runtime\Compiled\Results\ResultInstructionInterface;

interface ResultSourceCompilerInterface
{
	public function compile(ReflectionNamedType $type): ?ResultInstructionInterface;
}
