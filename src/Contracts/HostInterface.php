<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Transport host responsible for accepting connections and dispatching operations.
 * Receives a compiled RuntimeInterface at start time — no knowledge of the build phase.
 */
interface HostInterface
{
	public function start(RuntimeInterface $runtime): void;

	public function stop(): void;
}
