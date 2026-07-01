<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Answers questions about system health.
 * Centralizes workers, memory, cpu, coroutines, pools, requests, events, etc.
 */
interface DiagnosticsInterface
{
	/** @return array<string, mixed> */
	public function stats(): array;

	public function memoryUsage(): int;

	public function cpuUsage(): float;

	public function activeCoroutines(): int;

	/** @return array<string, mixed> */
	public function getPoolStats(?string $poolName = null): array;
}
