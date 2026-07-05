<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionParameter;
use Rokke\Runtime\Attribute\NotBlank;

final class NotBlankValidationSourceCompiler implements ValidationSourceCompilerInterface
{
	public function compile(ReflectionParameter $param): ?array
	{
		if ($param->getAttributes(NotBlank::class) === []) {
			return null;
		}

		return [new NotBlankValidationInstruction()];
	}
}
