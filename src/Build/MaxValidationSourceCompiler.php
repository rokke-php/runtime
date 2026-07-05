<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionParameter;
use Rokke\Runtime\Attribute\Max;

final class MaxValidationSourceCompiler implements ValidationSourceCompilerInterface
{
	public function compile(ReflectionParameter $param): ?array
	{
		$attrs = $param->getAttributes(Max::class);

		if ($attrs === []) {
			return null;
		}

		$max = $attrs[0]->newInstance()->value;

		return [new MaxValidationInstruction($max)];
	}
}
