<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Global Runtime state (not to be confused with per-request Context).
 * Useful for orchestration, health checks, and deployments.
 */
interface RuntimeStateInterface
{
	public function set(string $component, string $status): void;

	public function get(string $component): ?string;

	public function isHealthy(): bool;

	/** @return array<string, string> */
	public function all(): array;
}
