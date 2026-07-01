<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Manages processes.
 * Knows about workers, task workers, custom processes, restarts, hot reload, and scaling.
 * Should never be mixed with HTTP.
 */
interface WorkerManagerInterface
{
	/** @return array<int, mixed> */
	public function workers(): array;

	/** @return array<int, mixed> */
	public function taskWorkers(): array;

	/** @return array<int, mixed> */
	public function customProcesses(): array;

	public function reload(): void;

	public function scale(int $workers, int $taskWorkers = 0): void;
}
