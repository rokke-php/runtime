<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Internal supervisor.
 * Monitors memory, leaks, slow workers, deadlocks, saturated pools and makes decisions
 * (e.g. mark worker for graceful restart).
 */
interface RuntimeSupervisorInterface
{
	public function checkMemoryLeaks(): void;

	public function checkSlowWorkers(): void;

	public function markWorkerForRestart(int $workerId, string $reason): void;
}
