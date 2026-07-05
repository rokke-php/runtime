<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionParameter;
use Rokke\Runtime\Attribute\Min;

final class MinValidationSourceCompiler implements ValidationSourceCompilerInterface
{
	public function compile(ReflectionParameter $param): ?array
	{
		$attrs = $param->getAttributes(Min::class);

		if ($attrs === []) {
			return null;
		}

		$min = $attrs[0]->newInstance()->value;

		return [new MinValidationInstruction($min)];
	}
}
