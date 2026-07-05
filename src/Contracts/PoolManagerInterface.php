<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\Resource\PoolStats;

interface PoolManagerInterface
{
	public function register(PoolConfig $config, callable $factory): void;

	public function acquire(string $poolName): mixed;

	public function release(string $poolName, mixed $resource): void;

	public function stats(string $poolName): PoolStats;

	/** @return array<string, PoolStats> */
	public function allStats(): array;

	public function drain(string $poolName, float $timeout = 30.0): void;

	public function closeAll(): void;
}
