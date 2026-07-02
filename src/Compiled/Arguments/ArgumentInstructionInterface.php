<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled\Arguments;

use Rokke\Runtime\Contracts\OperationContextInterface;

interface ArgumentInstructionInterface
{
	public function resolve(OperationContextInterface $context): mixed;
}
