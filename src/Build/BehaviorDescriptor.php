<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Contracts\ExecutionBehaviorInterface;

final readonly class BehaviorDescriptor
{
	public function __construct(
		public ExecutionBehaviorInterface $behavior,
	) {}
}
