<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Translates system signals into Runtime events.
 */
interface SignalManagerInterface
{
	public function listen(int $signo, callable $handler): void;

	public function remove(int $signo): void;
}
