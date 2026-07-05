<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionParameter;
use Rokke\Runtime\Compiled\Arguments\ArgumentInstructionInterface;

interface ArgumentSourceCompilerInterface
{
	/** Returns null when this source cannot handle the parameter. */
	public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface;
}
