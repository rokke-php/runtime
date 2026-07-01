<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Contracts\Lifecycle\ApplicationState;

interface LifecycleManagerInterface
{
	public function transitionTo(ApplicationState $state): void;

	public function currentState(): ApplicationState;

	public function canTransitionTo(ApplicationState $state): bool;
}
