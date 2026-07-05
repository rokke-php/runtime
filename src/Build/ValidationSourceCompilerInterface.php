<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionParameter;

interface ValidationSourceCompilerInterface
{
	/** @return list<ValidationInstructionInterface>|null null when source does not apply to this parameter */
	public function compile(ReflectionParameter $param): ?array;
}
